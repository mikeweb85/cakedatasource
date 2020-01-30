<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Datasource\Predis;

use MikeWeb\Dsn\Dsn;
use InvalidArgumentException;
use Cake\Utility\Text;
use Cake\Utility\Hash;
use MikeWeb\CakeSources\Datasource\Predis\Connection;
use Cake\Core\StaticConfigTrait;
use Cake\Core\Exception\Exception as CakeException;
use Cake\Datasource\ConnectionManager as CakeConnectionManager;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;


class ConnectionManager extends CakeConnectionManager {
    
    /**
     * An array mapping url schemes to redis driver class names
     *
     * @var string[]
     * @psalm-var array<string, class-string>
     */
    protected static $_dsnClassMap = [
        'tcp'           => 'tcp',
        'redis'         => 'tcp',
        'tls'           => 'tls',
        'rediss'        => 'tls',
        'unix'          => 'unix',
        'socket'        => 'unix',
    ];
    
    /**
     * Parses a DSN into a valid connection configuration
     *
     * This method allows setting a DSN using formatting similar to that used by PEAR::DB.
     * The following is an example of its usage:
     *
     * ```
     * $dsn = 'tcp://redis:secret@localhost:6379/database/hash?prefix=app_&timeout=30&replication=sentinel&service=my_service';
     * ```
     *
     * Note that query-string arguments are also parsed and set as values in the returned configuration.
     *
     * @param string $config The DSN string to convert to a configuration array
     * @return array The configuration array to be stored after parsing the DSN
     */
    public static function parseDsn(string $config): array {
        $dsn = new Dsn($config);
        
        
        unset($dsn, $config['dsn'], $config['path']);
        
        return $config;
    }
    
    public static function connect(string $key, array $config=null): bool {
        
    }
    
    public static function disconnect(string $key): void {
        
    }
    
    public static function getConnection(string $key): Connection {
        
    }
}