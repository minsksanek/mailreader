<?php

namespace Minsksanek\Mailreader;

use Illuminate\Http\Request;
use Minsksanek\Mailreader\ClientController;

class FoldersController
{
    /*
     * Обрабатываем информацию о почтовых ящиках после выполнения imap_getmailboxes
     * https://www.php.net/manual/ru/function.imap-getmailboxes.php
     * */

    // Текущее соединение
    protected $client;
    // Путь
    public $path;
    // Имя папки
    public $name;
    // Полное имя папки
    public $full_name;
    // Подчиненные папки
    public $children = [];
    // Делиметр папок
    public $delimiter;
    // Этот ящик не имеет и не может иметь потомков (содержать вложенные ящики)
    public $no_inferiors;
    // Это только контейнер, а не почтовый ящик. Вы не можете его открыть
    public $no_select;
    // Этот ящик помечен. Означает, что в нем могут быть новые письма, появившиеся с момента последней проверк
    public $marked;
    // Этот почтовый ящик имеет выбираемые подчиненные (inferiors)
    public $has_children;
    // Этот контейнер имеет направления (referral) на удаленный почтовый ящик
    public $referal;

    /**
     * FoldersController constructor.
     * @param \Minsksanek\Mailreader\ClientController $client
     * @param $structure
     */
    public function __construct(ClientController $client, $structure) {
        $this->client = $client;
        $this->setDelimiter($structure->delimiter);
        $this->path      = $structure->name;
        $this->full_name  = $this->decodeName($structure->name);
        $this->name      = $this->getSimpleName($this->delimiter, $this->full_name);
        $this->parseAttributes($structure->attributes);
    }

    /**
     * @param $attributes
     */
    protected function parseAttributes($attributes) {
        $this->no_inferiors = ($attributes & LATT_NOINFERIORS) ? true : false;
        $this->no_select    = ($attributes & LATT_NOSELECT) ? true : false;
        $this->marked       = ($attributes & LATT_MARKED) ? true : false;
        $this->referal      = ($attributes & LATT_REFERRAL) ? true : false;
        $this->has_children = ($attributes & LATT_HASCHILDREN) ? true : false;
    }

    /**
     * @param $delimiter
     */
    public function setDelimiter($delimiter){
        if(in_array($delimiter, [null, '', ' ', false]) === true) {
            $delimiter ='/';
        }
        $this->delimiter = $delimiter;
    }

    /**
     * Декодируем имя
     * @param $name
     * @return bool|null|string|string[]
     */
    protected function decodeName($name) {
        preg_match('#\{(.*)\}(.*)#', $name, $preg);
        return mb_convert_encoding($preg[2], "UTF-8", "UTF7-IMAP");
    }

    /**
     * Имя папки
     * @param $delimiter
     * @param $full_name
     * @return mixed
     */
    protected function getSimpleName($delimiter, $full_name) {
        $arr = explode($delimiter, $full_name);
        return end($arr);
    }

    /**
     * @return mixed
     */
    public function hasChildren() {
        return $this->has_children;
    }

    /**
     * Вложенные папки
     * @param array $children
     * @return $this
     */
    public function setChildren($children = []) {
        $this->children = $children;
        return $this;
    }

    /**
     * @return \Minsksanek\Mailreader\ClientController
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Просмотр сообщений в нужной папке
     * @param string $charset
     * @return SearchController
     */
    public function messages($charset = 'UTF-8') {
        $this->getClient()->openFolder($this->path); //заходим в нужную папку
        return new SearchController($this->getClient(), $charset);
    }
}
