<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use Adige\core\database\dialects\mysql\DsnBuilder as MysqlDsnBuilder;
use Adige\core\database\dialects\sqlite\DsnBuilder as SqliteDsnBuilder;
use Adige\core\database\exceptions\CantConnectException;
use Adige\core\database\exceptions\ConnectionNameAlreadyExistsException;
use Adige\core\database\exceptions\DefaultConnectionNotDefinedException;
use Adige\core\database\exceptions\UnsupportedConnectionTypeException;
use PDO;
use PDOException;
use PDOStatement;

class Connection extends BaseObject
{
    public static array $connections = [];

    public const TYPE_MYSQL = 'mysql';
    public const TYPE_SQLITE = 'sqlite';
    private const TRANSACTION_CALLER_INTERNAL = 'internal';
    private const TRANSACTION_CALLER_EXTERNAL = 'external';

    private ?PDO $db = null;

    private bool $inTransaction = false;

    private bool $connected = false;

    private ?string $transactionCaller = null;

    private ?string $host;

    private string $name = '';

    private string $type = self::TYPE_MYSQL;

    private ?string $user;

    private ?string $password;

    private ?string $database;

    private ?string $port;

    private ?string $charset;

    private bool $default = false;

    private bool $requestedDefault = true;


    const AUTO_COMMIT = 'autoCommit';

    private array $options = [
        self::AUTO_COMMIT => false,
    ];


    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $database
     * @param string $port
     * @param string $charset
     * @param string $type
     * @param string $name
     * @param bool $isDefault
     * @param bool $autoCommit
     */
    public function __construct(
        string $host = '',
        string $user = '',
        string $password = '',
        string $database = '',
        string $port = '3306',
        string $charset = 'utf8mb4',
        string $type = self::TYPE_MYSQL,
        string $name = '',
        bool $isDefault = true,
        bool $autoCommit = false
    ) {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
        $this->charset = $charset;
        $this->type = $type;
        $this->name = $name !== '' ? $name : $host;
        $this->requestedDefault = $isDefault;
        $this->options[self::AUTO_COMMIT] = $autoCommit;
        $this->connect();
        parent::__construct();
    }

    /**
     * @return void
     * @throws CantConnectException
     * @throws ConnectionNameAlreadyExistsException
     */
    public function connect(): void
    {
        if (!$this->connected) {
            $isFirstConnection = empty(self::$connections);
            $this->assertNameIsAvailable();

            try {
                $this->db = new PDO(
                    $this->createDsnBuilder()->build($this),
                    $this->user,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                $this->connected = true;
                self::$connections[$this->name] = $this;

                if ($isFirstConnection && $this->requestedDefault) {
                    $this->makeDefault();
                }
            } catch (PDOException $exception) {
                throw new CantConnectException($this->host, $this->user, $this->password, $this->database, $this->charset, $exception);
            }
        }
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function makeDefault(): self
    {
        foreach (self::$connections as $connection) {
            $connection->default = false;
        }

        $this->default = true;

        return $this;
    }

    public function setOptions(array $options): void
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
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

    /**
     * @throws CantConnectException
     */
    public function beginTransaction(): self
    {
        return $this->beginTransactionFor(self::TRANSACTION_CALLER_EXTERNAL);
    }

    public function commitTransaction(): self
    {
        if ($this->inTransaction) {
            $this->db->commit();
            $this->inTransaction = false;
            $this->transactionCaller = null;
        }

        return $this;
    }

    private function autoCommit(): void
    {
        if (
            $this->options[self::AUTO_COMMIT] === true
            && $this->transactionCaller === self::TRANSACTION_CALLER_INTERNAL
        ) {
            $this->commitTransaction();
        }
    }

    public function rollBackTransaction(): self
    {
        if ($this->inTransaction) {
            $this->db->rollBack();
            $this->inTransaction = false;
            $this->transactionCaller = null;
        }

        return $this;
    }

    public function query($query = '', $args = []): PDOStatement
    {
        return $this->common($query, $args);
    }

    public function select(string $query, ?array $args = []): array
    {
        return $this->common($query, $args)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(string $query, ?array $args = []): string|int
    {
        $this->common($query, $args);
        return $this->db->lastInsertId();
    }

    public function update(string $query, ?array $args = []): int
    {
        return $this->common($query, $args)->rowCount();
    }

    public function delete(string $query, ?array $args = []): int
    {
        return $this->common($query, $args)->rowCount();
    }

    private function common(string $query, ?array $args = []): PDOStatement
    {
        $this->beginTransactionFor(self::TRANSACTION_CALLER_INTERNAL);
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new PDOException('Failed to prepare query.');
        }

        $stmt->execute($args);
        $this->autoCommit();

        return $stmt;
    }

    /**
     * @throws CantConnectException
     */
    private function beginTransactionFor(string $caller): self
    {
        $this->connect();

        if ($caller === self::TRANSACTION_CALLER_EXTERNAL) {
            $this->transactionCaller = self::TRANSACTION_CALLER_EXTERNAL;
        }

        if ($this->inTransaction) {
            return $this;
        }

        if ($this->db->beginTransaction()) {
            $this->inTransaction = true;
            $this->transactionCaller = $caller;
        }

        return $this;
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

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    protected function createDsnBuilder(): DsnBuilder
    {
        return match ($this->type) {
            self::TYPE_MYSQL => new MysqlDsnBuilder(),
            self::TYPE_SQLITE => new SqliteDsnBuilder(),
            default => throw new UnsupportedConnectionTypeException($this->type),
        };
    }

    /**
     * @throws ConnectionNameAlreadyExistsException
     */
    private function assertNameIsAvailable(): void
    {
        if (isset(self::$connections[$this->name])) {
            throw new ConnectionNameAlreadyExistsException($this->name);
        }
    }

}
