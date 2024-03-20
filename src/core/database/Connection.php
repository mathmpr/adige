<?php

namespace Adige\core\database;

use Adige\core\database\exceptions\CantConnectException;
use Adige\core\database\exceptions\DefaultConnectionNotDefinedException;
use PDO;
use PDOException;
use PDOStatement;
use Adige\core\BaseObject;
use Adige\core\BaseException;

class Connection extends BaseObject
{
    public static array $connections = [];

    private ?PDO $db = null;

    private bool $inTransaction = false;

    private bool $connected = false;

    private array $exceptions = [];

    private ?string $host;

    private ?string $user;

    private ?string $password;

    private ?string $database;

    private ?string $charset;

    private bool $default = false;


    const AUTO_COMMIT = 'autoCommit';

    private array $options = [
        self::AUTO_COMMIT => false,
    ];


    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $database
     * @param string $charset
     */
    public function __construct(
        string $host = '',
        string $user = '',
        string $password = '',
        string $database = '',
        string $charset = 'utf8mb4'
    ) {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->charset = $charset;
        parent::__construct();
    }

    /**
     * @return void
     * @throws CantConnectException
     */
    public function connect(): void
    {
        if (!$this->connected) {
            try {
                $this->db = new PDO(
                    "mysql:host=" . $this->host . (!empty($this->database) ? ";dbname=" . $this->database : "") . ";charset=" . $this->charset,
                    $this->user,
                    $this->password
                );
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connected = true;
            } catch (PDOException $exception) {
                $this->handleException($exception);
                throw new CantConnectException($this->host, $this->user, $this->password, $this->database, $this->charset, $exception);
            }
        }
    }

    public function setIsDefault(bool $default): void
    {
        $this->default = $default;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setOptions(array $options): void
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * @param PDOException|BaseException $exception
     * @return void
     */
    private function handleException(PDOException|BaseException $exception): void
    {
        $arrayException = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];

        $trace = array_values(array_filter($exception->getTrace(), function ($trace) {
            if(!isset($trace['file'])) {
                return false;
            }
            return $trace['file'] !== __FILE__;
        }));

        $arrayException['line'] = $trace[0]['line'] ?? 'unknown';
        $arrayException['file'] = $trace[0]['file'] ?? 'unknown';
        $arrayException['trace'] = $trace;

        $this->exceptions[] = $arrayException;
    }

    public function getOption($name = ''): array
    {
        if (!empty($name)) {
            return $this->options[$name];
        }
        return $this->options;
    }

    public function setOption(string $option, $value): void
    {
        if (isset($this->options[$option])) {
            $this->options[$option] = $value;
        }
    }

    public function getLastException()
    {
        return end($this->exceptions);
    }

    /**
     * @return void
     * @throws CantConnectException
     */
    public function beginTransaction(): void
    {
        $this->connect();
        if (!$this->inTransaction) {
            $this->inTransaction = true;
            $this->db->beginTransaction();
        }
    }

    public function commitTransaction(): void
    {
        if ($this->inTransaction) {
            $this->inTransaction = false;
            $this->db->commit();
        }
    }

    private function autoCommit(): void
    {
        if ($this->options[Connection::AUTO_COMMIT] === true) {
            $this->commitTransaction();
        }
    }

    public function rollBackTransaction(): void
    {
        if ($this->inTransaction) {
            $this->inTransaction = false;
            $this->db->rollBack();
        }
    }

    public function query($query = '', $args = []): ?PDOStatement
    {
        return $this->common($query, $args);
    }

    public function select(string $query, ?array $args = []): ?array
    {
        if ($stmt = $this->common($query, $args)) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    public function insert(string $query, ?array $args = []): int
    {
        if ($this->common($query, $args)) {
            return $this->db->lastInsertId();
        }
        return 0;
    }

    public function update(string $query, ?array $args = []): bool
    {
        return !empty($this->common($query, $args));
    }

    private function common(string $query, ?array $args = []): ?PDOStatement
    {
        $this->beginTransaction();
        try {
            $stmt = $this->db->prepare($query);
            if ($stmt && $stmt->execute($args)) {
                $this->autoCommit();
                return $stmt;
            }
        } catch (PDOException $exception) {
            $this->handleException($exception);
            $this->rollBackTransaction();
        }
        return null;
    }

    /**
     * @param array $config
     * @return void
     * @throws CantConnectException
     */
    public static function setDefaultConnection(array $config = []): void
    {
        $connection = new Connection(
            $config['host'] ?? '',
            $config['user'] ?? '',
            $config['password'] ?? '',
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );
        $connection->setIsDefault(true);
        $connection->setOptions($config['options'] ?? []);
        $connection->connect();
        self::$connections[] = $connection;
    }

    /**
     * @return Connection
     * @throws DefaultConnectionNotDefinedException
     */
    public static function getDefaultConnection(): Connection
    {
        foreach (self::$connections as $connection) {
            if ($connection->default) {
                return $connection;
            }
        }
        throw new DefaultConnectionNotDefinedException();
    }

    public function getDb(): ?PDO
    {
        return $this->db;
    }

    public function setDb(?PDO $db): Connection
    {
        $this->db = $db;
        return $this;
    }

}