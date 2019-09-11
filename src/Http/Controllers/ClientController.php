<?php
namespace Minsksanek\Mailreader;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use \Exception;
use Minsksanek\Mailreader\FoldersController;

class ClientController extends Controller
{
    /*
     * Управление соединением:
     * - Открывает соединение
     * - Закрывает соединение
     * - Просмотр папок на почте
     * - Установка текущей папки
     * https://www.php.net/manual/ru/function.imap-open.php
     * Позже будет добавить маску для поиска вложений
     * */

    // Текущее соединение
    public $connection = false;
    // Полное доменное имя сервера, либо IP-адрес в квадратных скобках
    public $host;
    // Определяет порт сервера
    public $port;
    // Сервис доступа к почтовому ящику. По умолчанию IMAP, может быть POP3 или NNTP.
    public $protocol;
    // Проверять сертификаты от серверов TLS/SSL
    public $validate_cert;
    // Шифрования сессии ['tls', 'notls', 'ssl']
    public $encryption;
    // Имя пользователя
    public $username;
    // Пароль пользователя username
    public $password;
    // Флаг о том что установлено соединение
    protected $connected = false;
    // Текущая папка нужна для переотрытия потока IMAP (смена папки)
    protected $active_folder = false;
    // Валидация для параметров
    protected $valid_config_keys = ['host', 'port', 'encryption', 'validate_cert', 'username', 'password', 'protocol'];
    // Конфигурация по умолчанию
    protected $default_config = [
        'host' => false,
        'port' => false,
        'encryption' => false,
        'validate_cert' => false,
        'username' => false,
        'password' => false,
        'protocol' => false
    ];
    // Шаблон маски вложений
    protected $default_attachment_mask = '';
    // Ошибки
    protected $errors = [];




    public function __construct($config = []) {
        foreach ($this->valid_config_keys as $key) {
            $this->$key = isset($config[$key]) ? $this->default_config[$key] = $config[$key] : $this->default_config[$key];
        }
        return $this;
    }

    // Конфиг
    /**
     * Формируем строку адреса для соединения
     * @return string
     */
    protected function getAddress() {
        $address = "{".$this->host.":".$this->port."/".($this->protocol ? $this->protocol : 'imap');
        if (!$this->validate_cert) {
            $address .= '/novalidate-cert';
        }
        if (in_array($this->encryption,['tls', 'notls', 'ssl'])) {
            $address .= '/'.$this->encryption;
        } elseif ($this->encryption === "starttls") {
            $address .= '/tls';
        }
        $address .= '}';
        return $address;
    }

    /**
     * Получим текущую конфигурацию
     * @return array
     */
    public function getConfig(){
        return $this->default_config;
    }


    // Работа с соединением
    /**
     * Создаем соединение с почтовым ящиком
     * @param int $attempts // Максимальное количество попыток соединения
     * @return $this
     */
    public function connect($attempts = 3) {
        $this->disconnect();
        try {
            $this->connection = imap_open(
                $this->getAddress(),
                $this->username,
                $this->password,
                0, // Битовая маска https://www.php.net/manual/ru/function.imap-open.php
                $attempts
            );
            $this->connected = !!$this->connection;
        } catch (\ErrorException $e) {
            $errors = imap_errors();
            $message = $e->getMessage().'. '.implode("; ", (is_array($errors) ? $errors : array()));
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Получим текущее соединение
     * @return mixed
     */
    public function getConnection() {
        $this->checkConnection();
        return $this->connection;
    }

    /**
     * Закрываем соединение
     * @return $this
     */
    public function disconnect() {
        if ($this->isConnected() && $this->connection !== false && is_integer($this->connection) === false) {
            $this->errors = array_merge($this->errors, imap_errors() ?: []);
            $this->connected = !imap_close($this->connection);
        }
        return $this;
    }

    /**
     * При завершении скрипта закрываем
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Проверяем есть ли соединение
     * @return mixed
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Проверяем не отвалилось ли соединение
     */
    public function checkConnection() {
        if (!$this->isConnected() || $this->connection === false) {
            $this->connect();
        }
    }


    // Работа с папками
    /**
     * Просмотр папок на почте
     * @param bool $hierarchical
     * @param null $parent_folder
     * @return mixed
     */
    public function getFolders($hierarchical = true, $parent_folder = null) {
        $this->checkConnection();
        $folders = Collection::make([]);
        $pattern = $parent_folder.($hierarchical ? '%' : '*');
        $items = imap_getmailboxes($this->connection, $this->getAddress(), $pattern);
        if(is_array($items)){
            foreach ($items as $item) {
                $folder = new FoldersController($this, $item);
                if ($hierarchical && $folder->hasChildren()) {
                    $pattern = $folder->full_name.$folder->delimiter.'%';
                    $children = $this->getFolders(true, $pattern);
                    $folder->setChildren($children);
                }
                $folders->push($folder);
            }
            return $folders;
        }else{
            throw new Exception(imap_last_error());
        }
    }

    /**
     * Установим текущую папку
     * @param mixed $client
     * @param mixed $structure
     */
    public function setFolder($client, $structure) {
        $this->client = $client;
        $this->setDelimiter($structure->delimiter);
        $this->path      = $structure->name;
        $this->full_name  = $this->decodeName($structure->name);
        $this->name      = $this->getSimpleName($this->delimiter, $this->full_name);
    }

    /**
     * Открытие конкретной папки
     * @param string $folder_path
     * @param int $attempts
     */
    public function openFolder($folder_path, $attempts = 3) {
        $this->checkConnection();
        if(property_exists($folder_path, 'path')) {
            $folder_path = $folder_path->path;
        }
        if ($this->active_folder !== $folder_path) {
            $this->active_folder = $folder_path;
            imap_reopen($this->getConnection(), $folder_path, 0, $attempts);
        }
    }

    /**
     * Текущая папка
     * @return string
     */
    public function getFolderPath(){
        return $this->active_folder;
    }




    /**
     * Шаблон маски вложений
     * @return string
     */
    public function getDefaultAttachmentMask(){
        return $this->default_attachment_mask;
    }
}
