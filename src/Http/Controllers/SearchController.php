<?php

namespace Minsksanek\Mailreader;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Minsksanek\Mailreader\ClientController;
use Minsksanek\Mailreader\MessageController;

class SearchController
{
    /*
     * Управление запросами на получение писем.
     * - выборка писем на дату
     * - выборка писем у котороых дата больше
     * - выборка непрочитанных писем
     * https://www.php.net/manual/ru/function.imap-search.php
     * */

    // Строка запроса
    protected $query;
    // Строка запроса форматированная
    protected $raw_query;
    // Кодировка
    protected $charset;
    // \Minsksanek\Mailreader\ClientController
    protected $client;
    // Формат даты
    protected $date_format;
    // Для валидации
    protected $available_criteria = [
        'OR', 'AND',
        'ALL', 'ANSWERED', 'BCC', 'BEFORE', 'BODY', 'CC', 'DELETED', 'FLAGGED', 'FROM', 'KEYWORD',
        'NEW', 'NOT', 'OLD', 'ON', 'RECENT', 'SEEN', 'SINCE', 'SUBJECT', 'TEXT', 'TO',
        'UNANSWERED', 'UNDELETED', 'UNFLAGGED', 'UNKEYWORD', 'UNSEEN'
    ];

    /**
     * @param Client $client
     * @param string $charset
     */
    public function __construct(ClientController $client, $charset = 'UTF-8') {
        $this->setClient($client);
        $this->charset = $charset;
        $this->date_format = 'd M y';
        $this->query = collect();
    }

    /**
     * Записываем соедиенение
     * @param \Minsksanek\Mailreader\ClientController $client
     * @return $this
     */
    public function setClient(ClientController $client) {
        $this->client = $client;
        return $this;
    }

    /**
     * Условие для выборки писем после "date"
     * SINCE "date" - сообщения с Date: после "date"
     * @param $value
     * @return SearchController
     */
    public function whereSince($value) {
        $date = $this->parse_date($value);
        return $this->where('SINCE', $date);
    }

    /**
     * Условие для выборки писем равным "date"
     * ON "date" - сообщения с Date: равным "date"
     * @param $value
     * @return SearchController
     */
    public function whereOnDate($value) {
        $date = $this->parse_date($value);
        return $this->where('ON', $date);
    }

    /**
     * Условие для выборки писем непрочтенные сообщения
     * @return SearchController
     */
    public function whereUNSEEN() {
        return $this->where('UNSEEN',NULL);
    }

    /**
     * Формирование даты
     * @param string $date
     * @return static
     */
    protected function parse_date($date) {
        if($date instanceof \Carbon\Carbon) return $date;
        try {
            $date = Carbon::parse($date);
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
        return $date;
    }

    /**
     * Формируем список параметров
     * @param $criteria
     * @param null $value
     * @return $this
     */
    public function where($criteria, $value = null) {
        if(is_array($criteria)){
            foreach($criteria as $arguments){
                if(count($arguments) == 1){
                    $this->where($arguments[0]);
                }elseif(count($arguments) == 2){
                    $this->where($arguments[0], $arguments[1]);
                }
            }
        }else{
            $criteria = $this->validate_criteria($criteria);
            $value = $this->parse_value($value);
            if($value === null || $value === ''){
                $this->query->push([$criteria]);
            }else{
                $this->query->push([$criteria, $value]);
            }
        }
        return $this;
    }

    /**
     * Проверка параметров на корректность
     * @param $criteria
     * @return string
     */
    protected function validate_criteria($criteria) {
        $criteria = strtoupper($criteria);
        if(in_array($criteria, $this->available_criteria) === false) {
            throw new Exception();
        }
        return $criteria;
    }

    /**
     * Если дата то приводим в нужный формат даты
     * @param $value
     * @return string
     */
    protected function parse_value($value){
        switch(true){
            case $value instanceof \Carbon\Carbon:
                $value = $value->format($this->date_format);
                break;
        }
        return (string) $value;
    }

    /**
     * По свормированному запросу получаем колекцию писем
     * @return Collection
     */
    public function get() {
        $messages = Collection::make([]);
        try {
            $available_messages = $this->search();
            $available_messages_count = $available_messages->count();
            if ($available_messages_count > 0) {

                $available_messages = $available_messages->reverse();
                $query =& $this;
                $available_messages->each(function($msgno, $msglist) use(&$messages, $query) {
                    //смотрим тело письма и вложения
                    $oMessage = new MessageController($msgno, $msglist, $query->client);
                    $message_key = $oMessage->getMessageId();
                    $messages->put($message_key, $oMessage);
                });
            }
            return $messages;
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    /**
     * Получить сообщения, удовлетворяющие заданным критериям
     * @return Collection
     */
    protected function search(){
        $this->generate_query();
        if($this->charset === null){
            $available_messages = imap_search($this->client->connection, $this->getRawQuery(), IMAP::SE_UID);
        }else{
            $available_messages = imap_search($this->client->connection, $this->getRawQuery(), IMAP::SE_UID, $this->charset);
        }
        if ($available_messages !== false) {
            return collect($available_messages);
        }
        return collect();
    }

    /**
     * @return string
     */
    public function getRawQuery() {
        return $this->raw_query;
    }

    /**
     * Формируем строку запроса для выборки писем
     * @return string
     */
    public function generate_query() {
        $query = '';
        $this->query->each(function($statement) use(&$query) {
            if (count($statement) == 1) {
                $query .= $statement[0];
            } else {
                if($statement[1] === null){
                    $query .= $statement[0];
                }else{
                    $query .= $statement[0].' "'.$statement[1].'"';
                }
            }
            $query .= ' ';
        });
        $this->raw_query = trim($query);
        return $this->raw_query;
    }
}
