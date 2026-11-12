<?php

namespace Tests\Unit;

use App\Services\FenixDemoSafety;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FenixDemoSafetyTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function configuration(): Repository
    {
        return new Repository([
            'app' => ['env' => 'local'],
            'database' => ['default' => 'mysql', 'connections' => ['mysql' => [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306',
                'database' => 'gymcontrol', 'url' => null, 'unix_socket' => '', 'prefix' => '',
            ]]],
            'fenix_demo' => ['allow_local_mysql' => true],
        ]);
    }

    public static function rejectedConfigurations(): array
    {
        return [
            'port' => ['database.connections.mysql.port', '3307'],
            'host' => ['database.connections.mysql.host', 'localhost'],
            'remote host' => ['database.connections.mysql.host', 'remote.test'],
            'demo database' => ['database.connections.mysql.database', 'gymcontrol_demo'],
            'restored database' => ['database.connections.mysql.database', 'gymcontrol_restore_test'],
            'mariadb driver' => ['database.connections.mysql.driver', 'mariadb'],
            'production' => ['app.env', 'production'],
            'demo environment' => ['app.env', 'demo'],
            'mysql testing' => ['app.env', 'testing'],
            'no opt in' => ['fenix_demo.allow_local_mysql', false],
            'unparsed opt in' => ['fenix_demo.allow_local_mysql', 'true'],
            'url' => ['database.connections.mysql.url', 'mysql://remote.test/other'],
            'socket' => ['database.connections.mysql.unix_socket', '/tmp/mysql.sock'],
            'read' => ['database.connections.mysql.read', ['host' => 'remote.test']],
            'write' => ['database.connections.mysql.write', ['host' => 'remote.test']],
            'prefix' => ['database.connections.mysql.prefix', 'other_'],
            'connect-time SQL' => ['database.connections.mysql.options', [PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND => 'USE other']],
        ];
    }

    #[DataProvider('rejectedConfigurations')]
    public function test_invalid_configuration_never_opens_a_database_connection(string $key, mixed $value): void
    {
        $config = $this->configuration();
        $config->set($key, $value);
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldNotReceive('connection');
        $guard = new FenixDemoSafety($config, $manager);
        $this->expectException(RuntimeException::class);
        $guard->run(fn () => $this->fail('An invalid target must never execute the loader.'));
    }

    public function test_sqlite_testing_must_use_memory_and_local_sqlite_is_not_a_mysql_bypass(): void
    {
        foreach ([['testing', 'file.sqlite'], ['local', ':memory:']] as [$environment, $database]) {
            $config = $this->configuration();
            $config->set('app.env', $environment);
            $config->set('database.connections.mysql', ['driver' => 'sqlite', 'database' => $database]);
            $manager = Mockery::mock(DatabaseManager::class);
            $manager->shouldNotReceive('connection');
            try {
                (new FenixDemoSafety($config, $manager))->run(fn () => $this->fail('Only isolated SQLite is permitted.'));
                $this->fail('An unsafe SQLite target must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Destino rechazado', $exception->getMessage());
            }
        }
    }

    private function session(Repository $config): array
    {
        $manager = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(Connection::class);
        $pdo = $this->createMock(PDO::class);
        $manager->shouldReceive('connection')->once()->with('mysql')->andReturn($connection);
        $manager->shouldReceive('purge')->once()->with('mysql');
        $connection->shouldReceive('getConfig')->once()->andReturn($config->get('database.connections.mysql'));
        $connection->shouldReceive('transactionLevel')->once()->andReturn(0);
        $connection->shouldReceive('setReconnector')->once()->with(Mockery::on(function ($reconnector): bool {
            try {
                $reconnector();
                $this->fail('Protected sessions must never reconnect.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('sesión MySQL', $exception->getMessage());
            }

            return true;
        }))->andReturnSelf();
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);

        return [new FenixDemoSafety($config, $manager), $pdo];
    }

    private function identity(PDO $pdo, string $database = 'gymcontrol', string $port = '3306', string $status = '127.0.0.1 via TCP/IP'): void
    {
        $pdo->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, 'mysql'], [PDO::ATTR_CONNECTION_STATUS, $status],
        ]);
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetch')->willReturn(['database_name' => $database, 'server_port' => $port]);
        $pdo->expects($this->once())->method('query')
            ->with('SELECT DATABASE() AS database_name, @@port AS server_port')->willReturn($statement);
    }

    public static function wrongIdentities(): array
    {
        return [
            ['other', '3306', '127.0.0.1 via TCP/IP'],
            ['gymcontrol', '3307', '127.0.0.1 via TCP/IP'],
            ['gymcontrol', '3306', 'localhost via UNIX socket'],
        ];
    }

    #[DataProvider('wrongIdentities')]
    public function test_actual_server_identity_is_checked_before_locking_or_loading(string $database, string $port, string $status): void
    {
        [$guard, $pdo] = $this->session($this->configuration());
        $this->identity($pdo, $database, $port, $status);
        $pdo->expects($this->never())->method('prepare');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identidad efectiva');
        $guard->run(fn () => $this->fail('The loader must not run on another server.'));
    }

    public function test_resolved_cached_configuration_is_checked_too(): void
    {
        $config = $this->configuration();
        $resolved = $config->get('database.connections.mysql');
        $resolved['database'] = 'other';
        $manager = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(Connection::class);
        $manager->shouldReceive('connection')->with('mysql')->andReturn($connection);
        $connection->shouldReceive('getConfig')->andReturn($resolved);
        $connection->shouldNotReceive('getPdo');
        $this->expectException(RuntimeException::class);
        (new FenixDemoSafety($config, $manager))->run(fn () => $this->fail('Resolved destination must be checked.'));
    }

    public function test_existing_transaction_is_rejected_without_disconnecting_the_caller(): void
    {
        $config = $this->configuration();
        $manager = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(Connection::class);
        $manager->shouldReceive('connection')->with('mysql')->andReturn($connection);
        $manager->shouldNotReceive('purge');
        $connection->shouldReceive('getConfig')->andReturn($config->get('database.connections.mysql'));
        $connection->shouldReceive('transactionLevel')->andReturn(1);
        $connection->shouldNotReceive('getPdo');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fuera de cualquier transacción');
        (new FenixDemoSafety($config, $manager))->run(fn () => $this->fail('Do not join another transaction.'));
    }

    public function test_pdo_driver_mismatch_prevents_the_loader(): void
    {
        [$guard, $pdo] = $this->session($this->configuration());
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');
        $pdo->expects($this->never())->method('query');
        $pdo->expects($this->never())->method('prepare');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('driver PDO');
        $guard->run(fn () => $this->fail('The PDO driver must match.'));
    }

    private function lockStatement(mixed $result): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())->method('execute')->with(['gymcontrol:fenix_demo:v1'])->willReturn(true);
        $statement->method('fetchColumn')->willReturn($result);

        return $statement;
    }

    public function test_existing_lock_rejects_another_load_without_releasing_its_lock(): void
    {
        [$guard, $pdo] = $this->session($this->configuration());
        $this->identity($pdo);
        $pdo->expects($this->once())->method('prepare')->with('SELECT IS_USED_LOCK(?)')->willReturn($this->lockStatement('42'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('en ejecución');
        $guard->run(fn () => $this->fail('Another lock owner prevents all loading.'));
    }

    public static function failedAcquisitions(): array
    {
        return [[0], [null]];
    }

    #[DataProvider('failedAcquisitions')]
    public function test_racing_or_unavailable_lock_fails_closed(mixed $result): void
    {
        [$guard, $pdo] = $this->session($this->configuration());
        $this->identity($pdo);
        $pdo->expects($this->exactly(2))->method('prepare')->willReturnCallback(fn ($sql) => match ($sql) {
            'SELECT IS_USED_LOCK(?)' => $this->lockStatement(null),
            'SELECT GET_LOCK(?, 0)' => $this->lockStatement($result),
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bloqueo exclusivo');
        $guard->run(fn () => $this->fail('A failed GET_LOCK must not run the loader.'));
    }

    public static function loadOutcomes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('loadOutcomes')]
    public function test_owned_lock_is_held_during_load_and_released_on_success_or_error(bool $throw): void
    {
        [$guard, $pdo] = $this->session($this->configuration());
        $this->identity($pdo);
        $loaded = false;
        $pdo->expects($this->exactly(3))->method('prepare')->willReturnCallback(function ($sql) use (&$loaded): PDOStatement {
            return match ($sql) {
                'SELECT IS_USED_LOCK(?)' => $this->lockStatement(null),
                'SELECT GET_LOCK(?, 0)' => $this->lockStatement('1'),
                'SELECT RELEASE_LOCK(?)' => (function () use (&$loaded): PDOStatement {
                    $this->assertTrue($loaded, 'The lock must remain held until the loader exits.');

                    return $this->lockStatement('1');
                })(),
            };
        });
        try {
            $guard->run(function () use (&$loaded, $throw): void {
                $loaded = true;
                if ($throw) {
                    throw new RuntimeException('Synthetic loader failure.');
                }
            });
            $this->assertFalse($throw);
        } catch (RuntimeException $exception) {
            $this->assertTrue($throw);
            $this->assertSame('Synthetic loader failure.', $exception->getMessage());
        }
        $this->assertTrue($loaded);
    }
}
