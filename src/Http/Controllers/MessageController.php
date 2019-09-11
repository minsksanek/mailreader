<?php

namespace Minsksanek\Mailreader;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Minsksanek\Mailreader\EncodingAliases;
use Minsksanek\Mailreader\ClientController;
use Minsksanek\Mailreader\AttachmentsController as Attachment;

class MessageController
{
    /*
     * Управление структрой писем. Здесь разбираем письмо выделяя заголовок, тело письма и вложения.
     * https://www.php.net/manual/ru/function.imap-fetchstructure.php
     * https://www.php.net/manual/ru/function.imap-fetchbody.php
     * https://www.php.net/manual/ru/function.imap-mime-header-decode.php
     * */

    // Текущее соединение
    private $client = ClientController::class;
    // Настройки соединения
    protected $config = [];
    // Проверяемые атрибуты письма
    protected $attributes = [
        'message_id' => '',
        'message_no' => null,
        'subject' => '',
        'references' => null,
        'date' => null,
        'from' => [],
        'to' => [],
        'cc' => [],
        'bcc' => [],
        'reply_to' => [],
        'in_reply_to' => '',
        'sender' => [],
    ];
    // Текущая папка
    protected $folder_path;
    // Опции для секции тела сообщения (битовая маска для imap_fetchbody)
    public $fetch_options = null;
    // Заголовок
    public $header = null;
    // Структура
    protected $structure = null;
    // Тело письма
    public $bodies = [];
    // Вложения
    public $attachments = [];

    /**
     * MessageController constructor.
     * @param $uid
     * @param $msglist
     * @param \Minsksanek\Mailreader\ClientController $client
     * @param null $fetch_options
     * @throws \Exception
     */
    public function __construct(
        $uid,
        $msglist,
        ClientController $client,
        $fetch_options = null
    ) {
        $this->folder_path = $client->getFolderPath();
        $this->config = $client->getConfig();
        $this->setFetchOption($fetch_options);
        $this->attachments = Collection::make([]);
        $this->msglist = $msglist;
        $this->client = $client;
        $this->uid =  ($this->fetch_options == IMAP::FT_UID) ? $uid : $uid;
        $this->msgn = ($this->fetch_options == IMAP::FT_UID) ? imap_msgno($this->client->getConnection(), $uid) : $uid;
        $this->parseHeader();
        $this->parseBody();
    }

    /** Определяем части в теле письма
     * @return $this
     */
    public function parseBody() {
        $this->client->openFolder($this->folder_path);
        $this->structure = imap_fetchstructure($this->client->getConnection(), $this->uid, IMAP::FT_UID);
        if(property_exists($this->structure, 'parts')){
            $parts = $this->structure->parts;
            foreach ($parts as $part)  {
                foreach ($part->parameters as $parameter)  {
                    if($parameter->attribute == "charset")  {
                        $encoding = $parameter->value;
                        $encoding = preg_replace('/Content-Transfer-Encoding/', '', $encoding);
                        $encoding = preg_replace('/iso-8859-8-i/', 'iso-8859-8', $encoding);
                        $parameter->value = $encoding;
                    }
                }
            }
        }
        $this->fetchStructure($this->structure);
        return $this;
    }

    /**
     * Устанавливаем атрибуты
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function setAttributes($name, $arguments) {
        if(in_array($name, array_keys($this->attributes))) {
            $this->attributes[$name] = $arguments;
            return $this->attributes[$name];
        }

        return $arguments;
    }

    /**
     * Парсим структуру письма
     * @param $structure
     * @param null $partNumber
     */
    private function fetchStructure($structure, $partNumber = null) {
        $this->client->openFolder($this->folder_path);
        if ($structure->type == IMAP::MESSAGE_TYPE_TEXT &&
            ($structure->ifdisposition == 0 ||
                ($structure->ifdisposition == 1 && !isset($structure->parts) && $partNumber != null)
            )
        ) {
            if (strtoupper($structure->subtype) == "PLAIN" && !isset($structure->disposition)) {
                if (!$partNumber) {
                    $partNumber = 1;
                }
                $encoding = $this->getEncoding($structure);
                $content = imap_fetchbody($this->client->getConnection(), $this->uid, $partNumber, $this->fetch_options | IMAP::FT_UID);
                $content = $this->decodeString($content, $structure->encoding);

                if ($encoding != 'us-ascii') {
                    $content = $this->convertEncoding($content, $encoding);
                }
                $body = new \stdClass;
                $body->type = "text";
                $body->content = $content;
                $this->bodies['text'] = $body;
                $this->fetchAttachment($structure, $partNumber);
            } elseif (strtoupper($structure->subtype) == "HTML") {
                if (!$partNumber) {
                    $partNumber = 1;
                }
                $encoding = $this->getEncoding($structure);
                $content = imap_fetchbody($this->client->getConnection(), $this->uid, $partNumber, $this->fetch_options | IMAP::FT_UID);
                $content = $this->decodeString($content, $structure->encoding);
                if ($encoding != 'us-ascii') {
                    $content = $this->convertEncoding($content, $encoding);
                }
                $body = new \stdClass;
                $body->type = "html";
                $body->content = $content;
                $this->bodies['html'] = $body;
            } elseif (strtoupper($structure->disposition) == 'ATTACHMENT') {
                $this->fetchAttachment($structure, $partNumber);
            }
        } elseif ($structure->type == IMAP::MESSAGE_TYPE_MULTIPART) {
            foreach ($structure->parts as $index => $subStruct) {
                $prefix = "";
                if ($partNumber) {
                    $prefix = $partNumber.".";
                }
                $this->fetchStructure($subStruct, $prefix.($index + 1));
            }
        } else {
            $this->fetchAttachment($structure, $partNumber);
        }
    }

    /**
     * Принудительное кодирование в UTF-8
     * @param $str
     * @param string $from
     * @param string $to
     * @return bool|null|string|string[]
     */
    public function convertEncoding($str, $from = "ISO-8859-2", $to = "UTF-8") {
        $from = EncodingAliases::get($from);
        $to = EncodingAliases::get($to);

        if ($from === $to) {
            return $str;
        }
        if (strtolower($from) == 'us-ascii' && $to == 'UTF-8') {
            return $str;
        }
        if (function_exists('iconv') && $from != 'UTF-7' && $to != 'UTF-7') {
            return @iconv($from, $to.'//IGNORE', $str);
        } else {
            if (!$from) {
                return mb_convert_encoding($str, $to);
            }
            return mb_convert_encoding($str, $to, $from);
        }
    }

    /**
     * Декодирование контента
     * @param $string
     * @param $encoding
     * @return string
     */
    public function decodeString($string, $encoding) {
        switch ($encoding) {
            case IMAP::MESSAGE_ENC_7BIT:
                return $string;
            case IMAP::MESSAGE_ENC_8BIT:
                return quoted_printable_decode(imap_8bit($string));
            case IMAP::MESSAGE_ENC_BINARY:
                return imap_binary($string);
            case IMAP::MESSAGE_ENC_BASE64:
                return imap_base64($string);
            case IMAP::MESSAGE_ENC_QUOTED_PRINTABLE:
                return quoted_printable_decode($string);
            case IMAP::MESSAGE_ENC_OTHER:
                return $string;
            default:
                return $string;
        }
    }

    /**
     * Получение кодировки
     * @param $structure
     * @return bool|false|mixed|string
     */
    public function getEncoding($structure) {
        if (property_exists($structure, 'parameters')) {
            foreach ($structure->parameters as $parameter) {
                if (strtolower($parameter->attribute) == "charset") {
                    return EncodingAliases::get($parameter->value);
                }
            }
        }elseif (is_string($structure) === true){
            return mb_detect_encoding($structure);
        }
        return 'UTF-8';
    }

    /**
     * Установка битовой маски
     * @param $option
     * @return $this
     */
    public function setFetchOption($option) {
        if (is_long($option) === true) {
            $this->fetch_options = $option;
        } elseif (is_null($option) === true) {
            $this->fetch_options = 1;
        }
        return $this;
    }

    /**
     * Извлекаем данные из оглавления письма
     * @throws \Exception
     */
    private function parseHeader() {
        $this->client->openFolder($this->folder_path);
        $this->header = $header = imap_fetchheader($this->client->getConnection(), $this->uid, IMAP::FT_UID);

        if ($this->header) {
            $header = imap_rfc822_parse_headers($this->header);
        }
        if (property_exists($header, 'subject')) {
            $this->subject = imap_utf8($header->subject);
            $this->setAttributes('subject',$this->subject);
        }
        foreach(['from', 'to', 'cc', 'bcc', 'reply_to', 'sender'] as $part){
            $this->extractHeaderAddressPart($header, $part);
        }
        if (property_exists($header, 'references')) {
            $this->references = $header->references;
            $this->setAttributes('references',$header->references);
        }
        if (property_exists($header, 'in_reply_to')) {
            $this->in_reply_to = str_replace(['<', '>'], '', $header->in_reply_to);
            $this->setAttributes('in_reply_to',$header->in_reply_to);
        }
        if (property_exists($header, 'message_id')) {
            $this->message_id = str_replace(['<', '>'], '', $header->message_id);
            $this->setAttributes('message_id',$header->message_id);
        }
        if (property_exists($header, 'Msgno')) {
            $messageNo = (int) trim($header->Msgno);
            $this->message_no = ($this->fetch_options == IMAP::FT_UID) ? $messageNo : imap_msgno($this->client->getConnection(), $messageNo);
        } else {
            $this->message_no = imap_msgno($this->client->getConnection(), $this->uid);
        }

        $this->setAttributes('message_no',$this->message_no);
        $this->date = $this->parseDate($header);
        $this->setAttributes('date',$this->date);
    }

    /**
     * Извлекаем части заколовка
     * @param $header
     * @param $part
     */
    private function extractHeaderAddressPart($header, $part) {
        if (property_exists($header, $part)) {
            $this->$part = $this->parseAddresses($header->$part);
            $this->setAttributes($part,$this->$part);
        }
    }

    /**
     * Отправитель письма
     * @param $list
     * @return array
     */
    private function parseAddresses($list) {
        $addresses = [];
        foreach ($list as $item) {
            $address = (object) $item;
            if (!property_exists($address, 'mailbox')) {
                $address->mailbox = false;
            }
            if (!property_exists($address, 'host')) {
                $address->host = false;
            }
            if (!property_exists($address, 'personal')) {
                $address->personal = false;
            }
            $personalParts = imap_mime_header_decode($address->personal);
            $address->personal = '';
            foreach ($personalParts as $p) {
                $address->personal .= $p->text;
            }
            $address->mail = ($address->mailbox && $address->host) ? $address->mailbox.'@'.$address->host : false;
            $address->full = ($address->personal) ? $address->personal.' <'.$address->mail.'>' : $address->mail;
            $addresses[] = $address;
        }
        return $addresses;
    }

    /**
     * Дата письма
     * @param $header
     * @return null|static
     * @throws \Exception
     */
    private function parseDate($header) {
        $parsed_date = null;
        if (property_exists($header, 'date')) {
            $date = $header->date;
            if(preg_match('/\+0580/', $date)) {
                $date = str_replace('+0580', '+0530', $date);
            }
            $date = trim(rtrim($date));
            try {
                $parsed_date = Carbon::parse($date);
            } catch (\Exception $e) {
                switch (true) {
                    case preg_match('/([0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ UT)+$/i', $date) > 0:
                    case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ UT)+$/i', $date) > 0:
                        $date .= 'C';
                        break;
                    case preg_match('/([A-Z]{2,3}[\,|\ \,]\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}.*)+$/i', $date) > 0:
                    case preg_match('/([A-Z]{2,3}\,\ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \(.*)\)+$/i', $date) > 0:
                    case preg_match('/([A-Z]{2,3}\, \ [0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{4}\ [0-9]{1,2}\:[0-9]{1,2}\:[0-9]{1,2}\ [\-|\+][0-9]{4}\ \(.*)\)+$/i', $date) > 0:
                    case preg_match('/([0-9]{1,2}\ [A-Z]{2,3}\ [0-9]{2,4}\ [0-9]{2}\:[0-9]{2}\:[0-9]{2}\ [A-Z]{2}\ \-[0-9]{2}\:[0-9]{2}\ \([A-Z]{2,3}\ \-[0-9]{2}:[0-9]{2}\))+$/i', $date) > 0:
                        $array = explode('(', $date);
                        $array = array_reverse($array);
                        $date = trim(array_pop($array));
                        break;
                }
                try{
                    $parsed_date = Carbon::parse($date);
                } catch (\Exception $_e) {
                    throw new \Exception("Invalid message date. ID:".$this->getMessageId(), 1000, $e);
                }
            }
        }
        return $parsed_date;
    }

    /**
     * Перебираем вложения письма
     * @param object $structure
     * @param mixed  $partNumber
     */
    protected function fetchAttachment($structure, $partNumber) {
        $oAttachment = new Attachment($this, $structure, $partNumber);
        if ($oAttachment->getName() !== null) {
            if ($oAttachment->getId() !== null) {
                $this->attachments->put($oAttachment->getId(), $oAttachment);
            } else {
                $this->attachments->push($oAttachment);
            }
        }
    }

    /**
     * ИД письма
     * @return string
     */
    public function getMessageId() {
        return $this->attributes['message_id'];
    }

    /**
     * Тема письма
     * @return string
     */
    public function getSubject() {
        return $this->attributes['subject'];
    }

    /**
     * Отправитель
     * @return array
     */
    public function getSender() {
        return $this->attributes['sender'];
    }

    /**
     * Текущее соединение
     * @return Client
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Получить вложения
     * @return array|static
     */
    public function getAttachments() {
        return $this->attachments;
    }

    /**
     * Получить тело письма
     * @return array
     */
    public function getBodies() {
        return $this->bodies;
    }
}