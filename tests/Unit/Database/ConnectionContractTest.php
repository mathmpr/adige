<?php

namespace Tests\Unit\Database;

use Adige\core\database\Connection;
use Adige\core\database\exceptions\CantConnectException;
use Adige\core\database\exceptions\DefaultConnectionNotDefinedException;
use PDO;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ConnectionContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Connection::$connections = [];
        parent::tearDown();
    }

    public function testGetDefaultConnectionThrowsWhenNoDefaultExists(): void
    {
        Connection::$connections = [];

        $this->expectException(DefaultConnectionNotDefinedException::class);

        Connection::getDefaultConnection();
    }

    public function testMakeDefaultPromotesConnectionAndGetDefaultConnectionReturnsIt(): void
    {
        $primary = $this->makeConnectionDouble('primary', true);
        $secondary = $this->makeConnectionDouble('secondary', false);

        Connection::$connections = [
            'primary' => $primary,
            'secondary' => $secondary,
        ];

        $secondary->makeDefault();

        self::assertFalse($primary->isDefault());
        self::assertTrue($secondary->isDefault());
        self::assertSame($secondary, Connection::getDefaultConnection());
    }

    public function testInvalidMysqlConnectionThrowsCantConnectException(): void
    {
        $this->expectException(CantConnectException::class);

        new Connection(
            host: '127.0.0.1',
            user: '',
            password: '',
            database: '',
            port: '1',
            type: Connection::TYPE_MYSQL,
            name: uniqid('invalid-', true),
            isDefault: false
        );
    }

    public function testCommitTransactionDoesNotCallPdoCommitWhenTransactionWasAutoClosed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('commit');
        $pdo->method('inTransaction')->willReturn(false);

        $connection = $this->makeConnectionDouble('stale', false);
        $this->setPrivateProperty($connection, 'db', $pdo);
        $this->setPrivateProperty($connection, 'inTransaction', true);
        $this->setPrivateProperty($connection, 'transactionCaller', 'external');

        $connection->commitTransaction();

        self::assertFalse($this->getPrivateProperty($connection, 'inTransaction'));
        self::assertNull($this->getPrivateProperty($connection, 'transactionCaller'));
    }

    public function testRollbackTransactionDoesNotCallPdoRollbackWhenTransactionWasAutoClosed(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('rollBack');
        $pdo->method('inTransaction')->willReturn(false);

        $connection = $this->makeConnectionDouble('stale', false);
        $this->setPrivateProperty($connection, 'db', $pdo);
        $this->setPrivateProperty($connection, 'inTransaction', true);
        $this->setPrivateProperty($connection, 'transactionCaller', 'external');

        $connection->rollBackTransaction();

        self::assertFalse($this->getPrivateProperty($connection, 'inTransaction'));
        self::assertNull($this->getPrivateProperty($connection, 'transactionCaller'));
    }

    private function makeConnectionDouble(string $name, bool $isDefault): Connection
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($connection, 'name', $name);
        $this->setPrivateProperty($connection, 'default', $isDefault);

        return $connection;
    }

    private function setPrivateProperty(object $object, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty(Connection::class, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty(object $object, string $name): mixed
    {
        $property = new \ReflectionProperty(Connection::class, $name);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
