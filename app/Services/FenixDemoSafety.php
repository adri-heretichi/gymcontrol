<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use PDO;
use RuntimeException;
use Throwable;

/** The MySQL session owns the advisory lock until the complete load has finished. */
class FenixDemoSafety
{
    private const LOCK = 'gymcontrol:fenix_demo:v1';

    private static bool $running = false;

    public function __construct(private Repository $config, private DatabaseManager $database) {}

    public function run(Closure $load): void
    {
        if (self::$running) {
            throw new RuntimeException('Ya hay una carga de demostración en ejecución en este proceso.');
        }

        $name = $this->config->get('database.default');
        $configured = $this->config->get("database.connections.{$name}", []);
        $this->assertConfiguration($configured);
        $connection = $this->database->connection($name);
        // Validate the resolved connection too: it may have been cached by DatabaseManager.
        $this->assertConfiguration($connection->getConfig());
        $mysql = $configured['driver'] === 'mysql';
        if ($mysql && $connection->transactionLevel() !== 0) {
            // Do not disconnect a caller's existing transaction when rejecting it.
            throw new RuntimeException('La carga MySQL debe iniciarse fuera de cualquier transacción existente.');
        }
        $locked = false;
        $pdo = null;
        $failure = null;
        self::$running = true;

        try {
            if ($mysql) {
                // A reconnection would lose GET_LOCK. Abort instead; purge restores normal
                // Laravel connection handling after this isolated seeder invocation.
                $connection->setReconnector(function (): never {
                    throw new RuntimeException('Se perdió la sesión MySQL protegida; la carga fue interrumpida.');
                });
            }
            $pdo = $connection->getPdo();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== $configured['driver']) {
                throw new RuntimeException('Destino rechazado: el driver PDO no coincide con la configuración.');
            }
            if ($mysql) {
                $identity = $pdo->query('SELECT DATABASE() AS database_name, @@port AS server_port')->fetch(PDO::FETCH_ASSOC);
                if (($identity['database_name'] ?? null) !== 'gymcontrol'
                    || (string) ($identity['server_port'] ?? '') !== '3306'
                    || $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) !== '127.0.0.1 via TCP/IP') {
                    throw new RuntimeException('Destino rechazado: la identidad efectiva de MySQL no es 127.0.0.1:3306/gymcontrol.');
                }
                // MySQL locks are reentrant on one session; reject even that case.
                $owner = $pdo->prepare('SELECT IS_USED_LOCK(?)');
                $owner->execute([self::LOCK]);
                if ($owner->fetchColumn() !== null) {
                    throw new RuntimeException('Ya hay una carga de demostración en ejecución; no se escribió ningún dato.');
                }
                $acquire = $pdo->prepare('SELECT GET_LOCK(?, 0)');
                $acquire->execute([self::LOCK]);
                if ((string) $acquire->fetchColumn() !== '1') {
                    throw new RuntimeException('No se pudo adquirir el bloqueo exclusivo de demostración; carga rechazada.');
                }
                $locked = true;
            }
            $load();
        } catch (Throwable $exception) {
            $failure = $exception;
            // Do not expose PDO diagnostics or connection options in CLI/log output.
            throw $exception instanceof \PDOException
                ? new RuntimeException('No se pudo verificar o mantener la sesión MySQL; carga rechazada.')
                : $exception;
        } finally {
            try {
                if ($locked) {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([self::LOCK]);
                    if ((string) $release->fetchColumn() !== '1' && $failure === null) {
                        throw new RuntimeException('La carga terminó, pero no se confirmó la liberación del bloqueo. Revise antes de reintentar.');
                    }
                }
            } catch (Throwable $releaseError) {
                if ($failure === null) {
                    throw new RuntimeException('No se pudo confirmar la liberación del bloqueo. Revise antes de reintentar.');
                }
            } finally {
                self::$running = false;
                if ($mysql) {
                    // Closes the session (and any residual advisory lock) without reconnecting.
                    $this->database->purge($name);
                }
            }
        }
    }

    private function assertConfiguration(array $connection): void
    {
        $environment = $this->config->get('app.env');
        if (!in_array($environment, ['local', 'testing'], true)) {
            throw new RuntimeException('El seeder solo se permite en local con MySQL autorizado o testing con SQLite en memoria.');
        }
        if (!empty($connection['url']) || !empty($connection['read']) || !empty($connection['write'])
            || !empty($connection['unix_socket']) || !empty($connection['prefix'])) {
            throw new RuntimeException('Destino rechazado: no se admiten DB_URL, read/write, socket ni prefijos alternativos.');
        }
        if ($environment === 'testing' && ($connection['driver'] ?? null) === 'sqlite'
            && ($connection['database'] ?? null) === ':memory:') {
            return;
        }
        if ($environment !== 'local' || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || (string) ($connection['port'] ?? '') !== '3306'
            || ($connection['database'] ?? null) !== 'gymcontrol'
            || $this->config->get('fenix_demo.allow_local_mysql') !== true
            || !empty($connection['options'][PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND] ?? null)) {
            throw new RuntimeException('Destino rechazado. Se requiere local, mysql, 127.0.0.1:3306/gymcontrol y FENIX_DEMO_ALLOW_LOCAL_MYSQL=true.');
        }
    }
}
