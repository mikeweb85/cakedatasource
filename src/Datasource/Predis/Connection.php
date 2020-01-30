<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Datasource\Predis;

use MikeWeb\Dsn\Dsn;
use InvalidArgumentException;
use Cake\Utility\Text;
use Cake\Utility\Hash;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\Exception\Exception;
use Predis\Client;
use Predis\ClientException;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
use Predis\Collection\Iterator\Keyspace;
use Cake\Datasource\ConnectionInterface;
use MikeWeb\CakeSources\Datasource\Predis\ConnectionManager;
use Cake\Database\TypeConverterTrait;
use Cake\Database\Schema\CollectionInterface;
use Cake\Database\StatementInterface;
use Cake\ORM\Query;
use Cake\Log\Log;
use MikeWeb\CakeSources\Exception\NotImplementedException;
use Psr\SimpleCache\CacheInterface;
use Cake\Cache\Cache;
use \Psr\Log\LoggerInterface;


class Connection implements ConnectionInterface {
    
    use InstanceConfigTrait;
    
    protected static $_registry;
    
    /**
     * Contains the configuration params for this connection.
     *
     * @var array
     */
    protected $_config;
    
    /**
     * Driver object, responsible for creating the real connection
     * and provide specific SQL dialect.
     *
     * @var \MikeWeb\CakeSources\Datasource\Predis\Client
     */
    protected $_driver;
    
    /**
     * Contains how many nested transactions have been started.
     *
     * @var int
     */
    protected $_transactionLevel = 0;
    
    /**
     * Whether a transaction is active in this connection.
     *
     * @var bool
     */
    protected $_transactionStarted = false;
    
    /**
     * Whether this connection can and should use savepoints for nested
     * transactions.
     *
     * @var bool
     */
    protected $_useSavePoints = false;
    
    /**
     * Whether to log queries generated during this connection.
     *
     * @var bool
     */
    protected $_logQueries = false;
    
    /**
     * Logger object instance.
     *
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $_logger;
    
    /**
     * Cacher object instance.
     *
     * @var \Psr\SimpleCache\CacheInterface|null
     */
    protected $cacher;
    
    /**
     * The schema collection object
     *
     * @var \Cake\Database\Schema\CollectionInterface|null
     */
    protected $_schemaCollection;
    
    /**
     * The default config used unless overridden by runtime configuration
     *
     * - `database` database number to use for connection.
     * - `duration` Specify how long items in this cache configuration last.
     * - `groups` List of groups or 'tags' associated to every key stored in this config.
     *    handy for deleting a complete group from cache.
     * - `password` Redis server password.
     * - `persistent` Connect to the Redis server with a persistent connection
     * - `port` port number to the Redis server.
     * - `prefix` Prefix appended to all entries. Good for when you need to share a keyspace
     *    with either another cache config or another application.
     * - `server` URL or ip to the Redis server host.
     * - `timeout` timeout in seconds (float).
     * - `unix_socket` Path to the unix socket file (default: false)
     *
     * @var array
     */
    protected $_defaultConfig = [
        'database'              => 0,
        'duration'              => 3600,
        'groups'                => [],
        'password'              => false,
        'persistent'            => true,
        'port'                  => 6379,
        'prefix'                => 'cake:',
        'host'                  => '127.0.0.1',
        'cluster'               => null,
        'replication'           => null,
        'service'               => null,
        'timeout'               => 5,
        'profile'               => null,
    ];
        
    protected function parseDsn(string $config): array {
        return ConnectionManager::parseDsn($config);
    }
    
    public function getDriver() {
        return $this->_driver;
    }
    
    /**
     * Destructor
     *
     * Disconnects the driver to release the connection.
     */
    public function __destruct(): void {
        if (class_exists(Log::class)) {
            Log::warning('The connection is going to be closed but there is an active transaction.');
        }
    }
    
    /**
     * @inheritDoc
     */
    public function config(): array {
        return $this->_config;
    }
    
    /**
     * @inheritDoc
     */
    public function configName(): string {
        if (empty($this->_config['name'])) {
            return '';
        }
        
        return $this->_config['name'];
    }
    
    /**
     * Connects to the configured database.
     *
     * @throws \Cake\Database\Exception\MissingConnectionException if credentials are invalid.
     * @return bool true, if the connection was already established or the attempt was successful.
     */
    public function connect(): bool {
    }
    
    /**
     * Disconnects from database server.
     *
     * @return void
     */
    public function disconnect(): void {
    }
    
    /**
     * Prepares a SQL statement to be executed.
     *
     * @param string|\Cake\Database\Query $sql The SQL to convert into a prepared statement.
     * @return \Cake\Database\StatementInterface
     */
    public function prepare($sql): StatementInterface {
        ## TODO: stubbed method
        throw new NotImplementedException();
    }
    
    /**
     * Executes a query using $params for interpolating values and $types as a hint for each
     * those params.
     *
     * @param string $query SQL to be executed and interpolated with $params
     * @param array $params list or associative array of params to be interpolated in $query as values
     * @param array $types list or associative array of types to be used for casting values in query
     * @return \Cake\Database\StatementInterface executed statement
     */
    public function execute(string $query, array $params = [], array $types = []): StatementInterface {
        throw new NotImplementedException();
        /* return $this->getDisconnectRetry()->run(function () use ($query, $params, $types) {
            if (!empty($params)) {
                $statement = $this->prepare($query);
                $statement->bind($params, $types);
                $statement->execute();
            } else {
                $statement = $this->query($query);
            }
            
            return $statement;
        }); */
    }
    
    /**
     * Executes a SQL statement and returns the Statement object as result.
     *
     * @param string $sql The SQL query to execute.
     * @return \Cake\Database\StatementInterface
     */
    public function query(string $sql): StatementInterface {
        /* return $this->getDisconnectRetry()->run(function () use ($sql) {
            $statement = $this->prepare($sql);
            $statement->execute();
            
            return $statement;
        }); */
    }
    
    /**
     * Create a new Query instance for this connection.
     *
     * @return \Cake\Database\Query
     */
    public function newQuery(): Query {
        return new Query($this);
    }
    
    /**
     * Gets a Schema\Collection object for this connection.
     *
     * @return \Cake\Database\Schema\CollectionInterface
     */
    public function getSchemaCollection(): CollectionInterface {
        if ($this->_schemaCollection !== null) {
            return $this->_schemaCollection;
        }
        
        return $this->_schemaCollection = new SchemaCollection($this);
    }
    
    /**
     * Returns whether the driver supports adding or dropping constraints
     * to already created tables.
     *
     * @return bool true if driver supports dynamic constraints
     */
    public function supportsDynamicConstraints(): bool {
        return false;
    }
    
    /**
     * {@inheritDoc}
     */
    public function transactional(callable $callback) {
        throw new NotImplementedException();
    }
    
    /**
     * {@inheritDoc}
     */
    public function disableConstraints(callable $callback) {
        throw new NotImplementedException();
    }
    
    /**
     * @inheritDoc
     */
    public function setCacher(CacheInterface $cacher) {
        throw new NotImplementedException();
    }
    
    /**
     * @inheritDoc
     */
    public function getCacher(): CacheInterface {
        /* if ($this->cacher !== null) {
            return $this->cacher;
        }
        
        $configName = $this->_config['cacheMetadata'] ?? '_cake_model_';
        if (!is_string($configName)) {
            $configName = '_cake_model_';
        }
        
        if (!class_exists(Cache::class)) {
            throw new RuntimeException(
                'To use caching you must either set a cacher using Connection::setCacher()' .
                ' or require the cakephp/cache package in your composer config.'
                );
        }
        
        return $this->cacher = Cache::pool($configName); */
        
        throw new NotImplementedException();
    }
    
    /**
     * Enable/disable query logging
     *
     * @param bool $value Enable/disable query logging
     * @return $this
     */
    public function enableQueryLogging(bool $value = true) {
        $this->_logQueries = $value;
        
        return $this;
    }
    
    /**
     * Disable query logging
     *
     * @return $this
     */
    public function disableQueryLogging() {
        $this->_logQueries = false;
        
        return $this;
    }
    
    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled(): bool {
        return $this->_logQueries;
    }
    
    /**
     * Sets a logger
     *
     * @param \Psr\Log\LoggerInterface $logger Logger object
     * @return $this
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function setLogger(LoggerInterface $logger) {
        $this->_logger = $logger;
        
        return $this;
    }
    
    /**
     * Gets the logger object
     *
     * @return \Psr\Log\LoggerInterface logger instance
     */
    public function getLogger(): LoggerInterface {
        if ($this->_logger !== null) {
            return $this->_logger;
        }
        
        return $this->_logger = Log::engine('debug');
    }
    
    /**
     * Logs a Query string using the configured logger object.
     *
     * @param string $command string to be logged
     * @return void
     */
    public function log(string $command): void {
        $this->getLogger()->debug($command);
    }
    
    /**
     * Returns an array that can be used to describe the internal state of this
     * object.
     *
     * @return array
     */
    public function __debugInfo(): array {
        $secrets = [
            'password' => '*****',
            'username' => '*****',
            'host' => '*****',
            'database' => '*****',
            'port' => '*****',
        ];
        
        $replace = array_intersect_key($secrets, $this->_config);
        $config = $replace + $this->_config;
        
        return [
            'config' => $config,
            'driver' => $this->_driver,
            'transactionLevel' => $this->_transactionLevel,
            'transactionStarted' => $this->_transactionStarted,
            'useSavePoints' => $this->_useSavePoints,
            'logQueries' => $this->_logQueries,
            'logger' => $this->_logger,
        ];
    }
}