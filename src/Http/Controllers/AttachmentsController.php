<?php

namespace Minsksanek\Mailreader;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AttachmentsController
{
    /*
     * Управление вложениями.
     * */

    // Текущее письмо
    protected $oMessage;
    // Конфиг
    protected $config = [];
    // Структура письма
    protected $structure;
    // Возможные атрибуты
    protected $attributes = [
        'part_number' => 1,
        'content' => null,
        'type' => null,
        'content_type' => null,
        'id' => null,
        'name' => null,
        'disposition' => null,
        'img_src' => null,
    ];

    /**
     * AttachmentsController constructor.
     * @param MessageController $oMessage
     * @param $structure
     * @param int $part_number
     */
    public function __construct(MessageController $oMessage, $structure, $part_number = 1) {
        $this->config = config('imap.options');
        $this->oMessage = $oMessage;
        $this->structure = $structure;
        $this->part_number = ($part_number) ? $part_number : $this->part_number;
        $this->findType();
        $this->fetch();
    }

    /**
     * Call dynamic attribute setter and getter methods
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     * @throws MethodNotFoundException
     */
    public function __call($method, $arguments) {
        if(strtolower(substr($method, 0, 3)) === 'get') {
            $name = snake_case(substr($method, 3));
            if(isset($this->attributes[$name])) {
                return $this->attributes[$name];
            }
            return null;
        }elseif (strtolower(substr($method, 0, 3)) === 'set') {
            $name = snake_case(substr($method, 3));
            $this->attributes[$name] = array_pop($arguments);
            return $this->attributes[$name];
        }
        throw new var_dump("Method ".self::class.'::'.$method.'() is not supported');
    }

    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value) {
        $this->attributes[$name] = $value;
        return $this->attributes[$name];
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name) {
        if(isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
        return null;
    }

    /**
     * Тип структуры
     */
    protected function findType() {
        switch ($this->structure->type) {
            case IMAP::ATTACHMENT_TYPE_MESSAGE:
                $this->type = 'message';
                break;
            case IMAP::ATTACHMENT_TYPE_APPLICATION:
                $this->type = 'application';
                break;
            case IMAP::ATTACHMENT_TYPE_AUDIO:
                $this->type = 'audio';
                break;
            case IMAP::ATTACHMENT_TYPE_IMAGE:
                $this->type = 'image';
                break;
            case IMAP::ATTACHMENT_TYPE_VIDEO:
                $this->type = 'video';
                break;
            case IMAP::ATTACHMENT_TYPE_MODEL:
                $this->type = 'model';
                break;
            case IMAP::ATTACHMENT_TYPE_TEXT:
                $this->type = 'text';
                break;
            case IMAP::ATTACHMENT_TYPE_MULTIPART:
                $this->type = 'multipart';
                break;
            default:
                $this->type = 'other';
                break;
        }
    }

    /**
     * Читаем части вложения
     */
    protected function fetch() {
        $content = imap_fetchbody($this->oMessage->getClient()->getConnection(), $this->oMessage->uid, $this->part_number, $this->oMessage->fetch_options | FT_UID);
        $this->content_type = $this->type.'/'.strtolower($this->structure->subtype);
        $this->content = $this->oMessage->decodeString($content, $this->structure->encoding);

        if (property_exists($this->structure, 'id')) {
            $this->id = str_replace(['<', '>'], '', $this->structure->id);
        }

        if (property_exists($this->structure, 'dparameters')) {
            foreach ($this->structure->dparameters as $parameter) {
                if (strtolower($parameter->attribute) == "filename") {
                    $this->setName($parameter->value);
                    $this->disposition = property_exists($this->structure, 'disposition') ? $this->structure->disposition : null;
                    break;
                }
            }
        }

        if (IMAP::ATTACHMENT_TYPE_MESSAGE == $this->structure->type) {
            if ($this->structure->ifdescription) {
                $this->setName($this->structure->description);
            } else {
                $this->setName($this->structure->subtype);
            }
        }

        if (!$this->name && property_exists($this->structure, 'parameters')) {
            foreach ($this->structure->parameters as $parameter) {
                if (strtolower($parameter->attribute) == "name") {
                    $this->setName($parameter->value);
                    $this->disposition = property_exists($this->structure, 'disposition') ? $this->structure->disposition : null;
                    break;
                }
            }
        }
    }

    /**
     * @param $name
     */
    public function setName($name) {
        if($this->config['decoder']['message']['subject'] === 'utf-8') {
            $this->name = imap_utf8($name);
        }else{
            $this->name = mb_decode_mimeheader($name);
        }
    }

    /**
     * @return null|string
     */
    public function getImgSrc() {
        if ($this->type == 'image' && $this->img_src == null) {
            $this->img_src = 'data:'.$this->content_type.';base64,'.base64_encode($this->content);
        }
        return $this->img_src;
    }

    /**
     * @return string|null
     */
    public function getMimeType(){
        return (new \finfo())->buffer($this->getContent(), FILEINFO_MIME_TYPE);
    }

    /**
     * Расширение
     * @return string|null
     */
    public function getExtension(){
        return ExtensionGuesser::getInstance()->guess($this->getMimeType());
    }

    /**
     * @return array
     */
    public function getAttributes(){
        return $this->attributes;
    }

    /**
     * @return Message
     */
    public function getMessage(){
        return $this->oMessage;
    }

    /**
     * Сохраняем
     * @param null $path
     * @param null $filename
     * @return bool
     */
    public function save($path = null, $filename = null) {
        $path = $path ?: storage_path();
        $filename = $filename ?: $this->getName();
        $path = substr($path, -1) == DIRECTORY_SEPARATOR ? $path : $path.DIRECTORY_SEPARATOR;
        return File::put($path.$filename, $this->getContent()) !== false;
    }
}
