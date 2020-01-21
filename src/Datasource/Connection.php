<?php

namespace CakePredis\Datasource;

use Exception as BaseException;
use InvalidArgumentException;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\Configure;
use Cake\Utility\Hash;
use Cake\Log\Log;
use Cake\Datasource\ConnectionInterface;
use Cake\Http\Exception\NotImplementedException;
use Cake\Database\Log\QueryLogger;
use ErrorException;
use Cake\Cache\Cache;
use Cake\Core\Exception\Exception;
use Cake\Database\Log\LoggedQuery;
use Predis\Client;
use Predis\ClientException;
// use Predis\Cluster\Distributor\HashRing;
// use Predis\Connection\Aggregate\PredisCluster;
// use Predis\Connection\Aggregate\MasterSlaveReplication;
use Predis\Connection\ConnectionInterface as PredisConnectionInterface;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
use Nyholm\DSN;
use Predis\Command\CommandInterface;


class Connection implements ConnectionInterface {
    
    use InstanceConfigTrait;
    
    /**
     * Debug mode
     * @var bool
     */
    protected $_debug = false;
    
    /**
     * Predis Client
     * @var \Predis\Client
     */
    protected $_driver;
    
    /**
     * Whether to log queries generated during this connection.
     * @var bool
     */
    protected $_logQueries = false;
    
    /**
     * Logger object instance.
     * @var \Cake\Database\Log\QueryLogger|null
     */
    protected $_logger;
    
    /**
     * Default configuration for instance
     * @var array
     */
    protected $_defaultConfig = [
        'database'              => 0,
        'duration'              => 3600,
        'prefix'                => 'cake_predis_',
        'probability'           => 100,
        'persistent'            => false,
        'groups'                => [],
        'host'                  => null,
        'port'                  => 6379,
        'password'              => null,
        'timeout'               => 5,
        'async'                 => false,
        'read_write_timeout'    => 60,
        'timeout'               => 30,
        'iterable_multibulk'    => false,
        'throw_errors'          => true,
        'profile'               => null,
        'fallback'              => null,
        'replication'           => null,
        'cluster'               => null,
        'aggregate'             => null,
        
        'log'                   => null,
        'debug'                 => false,
    ];
    
    /**
     * @param string $dsn
     * @return boolean|array[]
     */
    public static function parseDSN($dsn) {
        $dsn = new DSN($dsn);
        
        if ( !$dsn->isValid() ) {
            return false;
        }
        
        $connections = $options= [];
        
        
        
        return [$connections, $options];
    }
    
    
    
    public function __construct(array $config) {
        var_dump( self::class ); die();
        
        if ( empty($config['debug']) ) {
            $config['debug'] = Configure::read('debug', false);
        }
        
        if ( strtolower($config['hosts']) == 'advanced' && isset($config['dsn']) ) {
            
        }
        
        $this->setConfig($config);
        
        $this->_debug = $this->_config['debug'];
        
        // self::setInstance($this, $this->_config['domain']);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * @param string $func
     * @param array $arguments
     * @return mixed
     */
    public function __call($func, $arguments=[]) {
        return $this->execute($this->createCommand($func, $arguments));
    }
    
    /**
     * @param string $func
     * @param string $commandClass
     * @throws \Exception
     * @return \App\Predis\Connection
     */
    public function defineCommand($func, $commandClass) {
        if ( !is_string($func) || !is_string($commandClass)) {
            throw new Exception("Both function name and command class are required to be strings");
        }
        
        try {
            $this->getDriver()->getProfile()->defineCommand($func, $commandClass);
            
        } catch (BaseException $e) {
            throw $e;
        }
        
        return $this;
    }
    
    /**
     * @return \Predis\Client
     */
    public function getDriver() {
        return $this->_driver;
    }
    
    /**
     * @return boolean
     */
    public function isConnected() {
        return $this->getDriver()->isConnected();
    }
    
    public function __destruct() {
        $this->disconnect();
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::configName()
     */
    public function configName() {
        if (empty($this->_config['name'])) {
            return '';
        }
        
        return $this->_config['name'];
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::transactional()
     */
    public function transactional(callable $callback) {
        try {
            $result = $callback($this);
            
        } catch (BaseException $e) {
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::disableConstraints()
     */
    public function disableConstraints(callable $operation) {
        throw new NotImplementedException();
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::logger()
     */
    public function logger($instance=null) {
        deprecationWarning('Connection::logger() is deprecated. Use Connection::setLogger()/getLogger() instead.');
        
        if ($instance === null) {
            return $this->getLogger();
        }
        
        $this->setLogger($instance);
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::getLogger()
     */
    public function getLogger() {
        if ($this->_logger === null) {
            $this->_logger = new QueryLogger();
        }
        
        return $this->_logger;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::setLogger($logger)
     */
    public function setLogger($logger) {
        $this->_logger = $logger;
        
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::supportsDynamicConstraints()
     */
    public function supportsDynamicConstraints() {
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::getSchemaCollection()
     */
    public function getSchemaCollection() {
        throw new NotImplementedException();
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::newQuery()
     */
    public function newQuery() {
        throw new NotImplementedException();
    }
    
    /**
     * @param string $func
     * @param array $params
     * @throws \Predis\ClientException
     * @return \Predis\Command\CommandInterface
     */
    public function prepare($func, array $params=[]) {
        return $this->getDriver()->getProfile()->createCommand($func, $params);
    }
    
    /**
     * @param \Predis\Command\CommandInterface|string $func
     * @param array $params
     * @throws \Predis\ClientException
     * @return mixed
     */
    public function execute($command, $params=[], array $types=[]) {
        if ( is_string($command) ) {
            $command = $this->prepare($command, $params);
            
        } elseif ( $command instanceof CommandInterface ) {
            if ( !empty($params) ) {
                $command->setArguments($params);
            }
            
        } else {
            throw new ClientException("Invalid command, must be instnace of \Predis\Command\CommandInterface");
        }
        
        return $this->getDriver()->executeCommand($command);
    }
    
    /**
     * @param string $func
     * @param array $params
     * @return mixed
     */
    public function executeRaw($func, $params=[]) {
        if ( !is_string($func) ) {
            throw new InvalidArgumentException("Invalid function, must be a string.");
        }
        
        $error = null;
        $commandArr = $params;
        
        // prepend the func to the struct
        array_unshift($commandArr, $func);
        
        try {
            $response = $this->getDriver()->executeRaw($commandArr, $error);
            
            if ( $error ) {
                throw new BaseException($response);
            }
            
        } catch (BaseException $e) {
            throw $e;
        }
        
        return $response;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::quote($value, $type)
     */
    public function quote($value, $type=null) {
        return $value;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::isQueryLoggingEnabled()
     */
    public function isQueryLoggingEnabled() {
        return $this->_logQueries;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::disableQueryLogging()
     */
    public function disableQueryLogging() {
        $this->enableQueryLogging(false);
        
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::logQueries()
     */
    public function logQueries($enable=null) {
        if ($enable === null) {
            return $this->isQueryLoggingEnabled();
        }
        
        $this->enableQueryLogging($enable);
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::enableQueryLogging($value)
     */
    public function enableQueryLogging($value=true) {
        $this->_logQueries = boolval($value);
        
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Datasource\ConnectionInterface::disableSavePoints()
     */
    public function disableSavePoints() {
        return $this;
    }
}