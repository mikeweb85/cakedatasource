<?php

namespace App\Amqp;

use Cake\Core\InstanceConfigTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Cake\Core\Configure;
use Cake\Utility\Hash;
use UnexpectedValueException;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use Cake\Database\Exception\MissingDriverException;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use Cake\Database\Exception\MissingExtensionException;
use PhpAmqpLib\Channel\AMQPChannel;
use Cake\Log\Log;
use Cake\Datasource\ConnectionInterface;
use Cake\Http\Exception\NotImplementedException;
use Cake\Database\Log\QueryLogger;
use App\Utility\Text;
use PhpAmqpLib\Wire\AMQPAbstractCollection;
use Cake\I18n\Time;
use ErrorException;
use Cake\Core\Exception\Exception;
use PhpAmqpLib;


class Connection implements ConnectionInterface {
    
    use InstanceConfigTrait;
    
    /**
     * Debug mode
     * @var bool
     */
    protected $_debug = false;
    
    /**
     * AMQP Connection
     * @var PhpAmqpLib\Connection\AbstractConnection
     */
    protected $_driver;
    
    /**
     * AMQP Channel
     * @var PhpAmqpLib\Channel\AMQPChannel
     */
    protected $_channel;
    
    /**
     * Whether to log queries generated during this connection.
     *
     * @var bool
     */
    protected $_logQueries = false;
    
    /**
     * Logger object instance.
     *
     * @var \Cake\Database\Log\QueryLogger|null
     */
    protected $_logger;
    
    /**
     * Array of registered consumers
     * @var array
     */
    protected $_consumers = [];
    
    protected $_map = [
        'amqp'              => 'PhpAmqpLib\Connection\AMQPStreamConnection',
        'amqps'             => 'PhpAmqpLib\Connection\AMQPSSLConnection',
    ];
    
    /**
     * Default configuration for instance
     * @var array
     */
    protected $_defaultConfig = [
        'host'                  => 'localhost',
        'port'                  => 5672,
        'username'              => 'guest',
        'password'              => 'guest',
        'vhost'                 => '/',
        'context'               => [
            'socket'                => [
                'tcp_nodelay'           => true,
            ],
        ],
        'insist'                => false,
        'login_method'          => 'AMQPLAIN',
        'login_response'        => null,
        'locale'                => 'en_US',
        'connection_timeout'    => 15.0,
        'read_write_timeout'    => 15.0,
        'heartbeat'             => 5,
        'keepalive'             => false,
        'log'                   => null,
        'debug'                 => false,
    ];
    
    /**
     * Array of instantiated connections
     * @var array
     */
    protected static $_instances = [];
    
    /**
     * 
     * @param Connection $connection
     * @param string $vhost
     * @return void
     * @throws Exception
     */
    public static function setInstance(Connection $connection, $vhost='/') {
        if ( isset(self::$_instances[$vhost]) && !empty(self::$_instances[$vhost]) ) {
            throw new Exception("Connection instance for vhost [${vhost}] already set.");
        }
        
        self::$_instances[$vhost] = $connection;
    }
    
    /**
     * 
     * @param string $vhost
     * @return Connection
     */
    public static function getInstance($vhost='/') {
        return self::$_instances[$vhost] ?: null;
    }
    
    public function __construct(array $config) {
        if ( empty($config['debug']) ) {
            $config['debug'] = Configure::read('debug', false);
        }
        
        if ( !empty($config['database']) ) {
            $config['vhost'] = $config['database'];
            unset($config['database']);
        }
        
        if ( isset($config['driver']) && $config['driver'] == self::class ) {
            unset($config['driver']);
        }
        
        if ( !empty($config['driver']) ) {
            $driver = $config['driver'];
            unset($config['driver']);
            
        } elseif ( !empty($config['scheme']) && $config['scheme'] != self::class ) {
            foreach ($this->_map as $mapScheme=>$mapDriver) {
                if ( $config['scheme'] == $mapScheme || $config['scheme'] == $mapDriver || (false === strpos($config['scheme'], '\\') && $config['scheme'] == @array_pop(explode('\\', $mapDriver))) ) {
                    $driver = $mapDriver;
                    
                    break;
                }
            }
        }
        
        if ( !isset($driver) ) {
            throw new Exception('A valid driver could not be parsed.');
        }
        
        $this->setConfig($config);
        
        $this->_debug = $this->_config['debug'];
        
        $this->setDriver($driver);
        
        self::setInstance($this, $this->_config['vhost']);
    }
    
    
    public function getStreamContextOptions() {
        return Hash::merge(Configure::read('Stream.Context.default.socket', []), $this->getConfig('context.socket', []));
    }
    
    
    public function getSslContextOptions() {
        return Hash::merge(Configure::read('Stream.Context.default.ssl', []), $this->getConfig('context.ssl', []));
    }
    
    
    public function setDriver($driver, $config=[]) {
        $config = Hash::merge($this->_config, $config);
        
        if ( is_string($driver) ) {
            if ( !class_exists($driver, true) ) {
                throw new MissingDriverException(['driver'=>$driver]);
            }
            
            switch ($driver) {
                case 'PhpAmqpLib\Connection\AMQPSSLConnection':
                    $sslOptions = $this->getSslContextOptions();
                    $driver = new AMQPSSLConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost'], $sslOptions, $config);
                    break;
                    
                case 'PhpAmqpLib\Connection\AMQPStreamConnection':
                    $context = stream_context_create($this->getStreamContextOptions());
                    $driver = new AMQPStreamConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost'], $config['insist'], $config['login_method'], $config['login_response'], $config['locale'], $config['connection_timeout'], $config['read_write_timeout'], $context, $config['keepalive'], $config['heartbeat']);
                    break;
                    
                case 'PhpAmqpLib\Connection\AMQPSocketConnection':
                    $driver = new AMQPSocketConnection($config['host'], $config['port'], $config['username'], $config['password'], $config['vhost'], $config['insist'], $config['login_method'], $config['login_response'], $config['locale'], $config['read_write_timeout'], $config['keepalive'], $config['read_write_timeout'], $config['heartbeat']);
                    break;
                    
                default:
                    throw new MissingDriverException(['driver'=>$driver]);
            }
        }
        
        if ( !($driver instanceof AbstractConnection) ) {
            throw new MissingExtensionException(['driver'=>get_class($driver)]);
        }
        
        if ( $this->_debug ) {
            Log::debug( vsprintf('RabbitMQ [%s] connected using driver [%s].', [$config['name'], get_class($driver)]) );
        }
        
        $this->_driver = $driver;
        
        return $this;
    }
    
    
    public function __destruct() {
        $this->disconnect();
    }
    
    
    public function disconnect() {
        if ( !empty($this->_channel) ) {
            try {
                $this->_channel->close();
                
            } catch (ErrorException $e) {
                
            }
        }
        
        if ( !empty($this->_driver) ) {
            try {
                $this->_driver->close();
                
            } catch (ErrorException $e) {
                
            }
        }
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
     public function transactional(callable $transaction) {
         throw new NotImplementedException();
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
      * @see \Cake\Datasource\ConnectionInterface::logQueries()
      */
     public function logQueries($enable=null) {
         if ($enable === null) {
             return $this->_logQueries;
         }
         
         $this->_logQueries = $enable;
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
      * @return \PhpAmqpLib\Channel\AMQPChannel
      */
     public function newQuery() {
         return $this->_driver->channel();
     }
    
     /**
      * {@inheritDoc}
      * @see \Cake\Datasource\ConnectionInterface::prepare($sql)
      */
     public function prepare($sql) {
         throw new NotImplementedException();
     }
    
     /**
      * {@inheritDoc}
      * @see \Cake\Datasource\ConnectionInterface::execute($query, $params, $types)
      */
     public function execute($query, $params=[], array $types=[]) {
         throw new NotImplementedException();
     }
    
     /**
      * {@inheritDoc}
      * @see \Cake\Datasource\ConnectionInterface::quote($value, $type)
      */
     public function quote($value, $type=null) {
         throw new NotImplementedException();
     }

     
     /**
      * Get the active channel
      * @param \PhpAmqpLib\Channel\AMQPChannel|integer|null $channel
      * @return \PhpAmqpLib\Channel\AMQPChannel
      */
     public function getChannel($channel=null) {
         if ( $channel instanceof AMQPChannel ) {
             return $channel;
             
         } elseif ( is_int($channel) ) {
             return $this->_driver->channel($channel);
             
         } elseif ( $channel !== null ) {
             throw new UnexpectedValueException();
         }
         
         if ( empty($this->_channel) ) {
             $this->setChannel($this->_driver->channel());
         }
         
         return $this->_channel;
     }
     
     
     public function setChannel(AMQPChannel $channel) {
         if ( !empty($this->_channel) ) {
             if ( $this->_debug ) {
                 Log::debug( vsprintf('RabbitMQ [%s] closing channel with ID [%s].', [$this->_config['name'], $this->_channel->getChannelId()]) );
             }
             
             $this->_channel->close();
         }
         
         if ( $this->_debug ) {
             Log::debug( vsprintf('RabbitMQ [%s] using channel with ID [%s].', [$this->_config['name'], $channel->getChannelId()]) );
         }
         
         $this->_channel = $channel;
     }
     
     
     public function declareExchange($name, $type, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         $options = Hash::merge([
             'passive'          => false,
             'durable'          => false,
             'auto_delete'      => true,
             'internal'         => false,
             'nowait'           => false,
             'ticket'           => null,
             'channel'          => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->exchange_declare($name, $type, $options['passive'], $options['durable'], $options['auto_delete'], $options['internal'], $options['nowait'], $arguments, $options['ticket']);
     }
     
     
     public function bindExchange($destination, $source, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         $options = Hash::merge([
             'routingKey'   => false,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->exchange_bind($destination, $source, $options['routingKey'], $options['nowait'], $arguments, $options['ticket']);
     }
     
     
     public function unbindExchange($destination, $source, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         $options = Hash::merge([
             'routingKey'   => false,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->exchange_unbind($destination, $source, $options['routingKey'], $options['nowait'], $arguments, $options['ticket']);
     }
     
     
     public function deleteExchange($name, array $options=[]) {
         $options = Hash::merge([
             'if_unused'    => false,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->exchange_delete($name, $options['if_unused'], $options['nowait'], $options['ticket']);
     }
     
     
     public function declareQueue($name, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         $options = Hash::merge([
             'passive'      => false,
             'durable'      => false,
             'exclusive'    => false,
             'auto_delete'  => true,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->queue_declare($name, $options['passive'], $options['durable'], $options['exclusive'], $options['auto_delete'], $options['nowait'], $arguments, $options['ticket']);
     }
     
     
     public function bindQueue($queue, $exchange, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         if ( !empty($arguments) ) {
             foreach ($arguments as $key=>$value) {
                 $arguments[$key] = ['S', $value];
             }
         }
         
         $options = Hash::merge([
             'routingKey'   => false,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->queue_bind($queue, $exchange, $options['routingKey'], $options['nowait'], $arguments, $options['ticket']);
     }
     
     
     public function unbindQueue($queue, $exchange, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([], $options['arguments']);
         }
         
         $options = Hash::merge([
             'routingKey'   => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->queue_unbind($queue, $exchange, $options['routingKey'], $arguments, $options['ticket']);
     }
     
     
     public function deleteQueue($name, array $options=[]) {
         $options = Hash::merge([
             'if_unused'    => false,
             'if_empty'     => false,
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->queue_delete($name, $options['if_unused'], $options['if_empty'], $options['nowait'], $options['ticket']);
     }
     
     
     public function purgeQueue($name, array $options=[]) {
         $options = Hash::merge([
             'nowait'       => false,
             'ticket'       => null,
         ], $options);
         
         return $this->getChannel()->queue_purge($name, $options['nowait'], $options['ticket']);
     }
     
     
     public function acknowledge($deliveryTag, array $options=[]) {
         $options = Hash::merge([
             'multiple'         => false,
             'nack'             => true,
             'requeue'          => false,
             'channel'          => null,
         ], $options);
         
         if ( $options['nack'] === true ) {
             return $this->getChannel($options['channel'])->basic_nack($deliveryTag, $options['multiple'], $options['requeue']);
         }
         
         return $this->getChannel($options['channel'])->basic_ack($deliveryTag, $options['multiple']);
     }
     
     
     public function dropConsumer($consumerTag, array $options=[]) {
         $options = Hash::merge([
             'nowait'           => false,
             'noreturn'         => false,
             'channel'          => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->basic_cancel($consumerTag, $options['nowait'], $options['noreturn']);
     }
     
     
     public function consume($queue, $callback, array $options=[]) {
         $arguments = [];
         
         if ( isset($options['arguments']) ) {
             if ( !is_array($options['arguments']) ) {
                 throw new UnexpectedValueException();
             }
             
             $arguments = Hash::merge([
                 'x-cancel-on-ha-failover'      => ['t', true]
             ], $options['arguments']);
         }
         
         $options = Hash::merge([
             'tag'           => null,
             'no_local'      => false,
             'no_ack'        => false,
             'exclusive'     => false,
             'nowait'        => false,
             'ticket'        => null,
         ], $options);
         
         $tag = $this->setConsumer($options['tag'], $queue, $callback);
         
         return $this->getChannel()->basic_consume($queue, $tag, $options['no_local'], $options['no_ack'], $options['exclusive'], false, [$this, 'processMessage'], $options['ticket'], $arguments);
     }
     
     
     public function publish($message, $exchange, array $properties=[], array $headers=[], array $options=[]) {
         $options = Hash::merge([
             'routingKey'       => null,
             'mandatory'        => false,
             'immediate'        => false,
             'ticket'           => null,
         ], $options);
         
         if ( is_string($message) ) {
             $message = new AMQPMessage($message);
         }
             
         if ( !($message instanceof AMQPMessage) ) {
             throw new UnexpectedValueException();
         }
         
         $properties = Hash::merge([
             'delivery_mode'        => AMQPMessage::DELIVERY_MODE_PERSISTENT,
         ], $properties);
         
         foreach ($properties as $property=>$propValue) {
             if ( $property == 'application_headers' ) {
                 continue;
             }
             
             $message->set($property, $propValue);
         }
         
         $headers = Hash::merge([
             'environment'       => strtolower(env('APPLICATION_ENVIRONMENT', 'dev')),
         ], $headers);
         
         $headers = new AMQPTable($headers);
         $message->set('application_headers', $headers);
         
         return $this->getChannel()->basic_publish($message, $exchange, $options['routingKey'], $options['mandatory'], $options['immediate'], $options['ticket']);
     }
     
     
     public function reject($deliveryTag, array $options=[]) {
         $options = Hash::merge([
             'requeue'       => false,
             'channe;'       => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->basic_reject($deliveryTag, $options['requeue']);
     }
     
     
     public function recover(array $options=[]) {
         $options = Hash::merge([
             'requeue'       => false,
             'channel'       => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->basic_recover($options['requeue']);
     }
     
     
     public function setConsumer($tag=null, $queue, $callback) {
        if ( empty($tag) ) {
            $tag = Text::uuid();
        }
        
        $this->_consumers[$tag] = [$queue, $callback];
        
        return $tag;
     }
     
     
     public function unsetConsumer($tag) {
         if ( !empty($this->_consumers[$tag]) ) {
             unset($this->_consumers[$tag]);
         }
     }
     
     
     public function getConsumer($tag) {
         if ( empty($this->_consumers[$tag]) ) {
             return false;
         }
         
         return $this->_consumers[$tag];
     }
     
     
     public function processMessage(AMQPMessage $message) {
         list($queue, $callback) = $this->getConsumer($message->delivery_info['consumer_tag']);
         
         $message->delivery_info['queue'] = $queue;
         
         call_user_func_array($callback, [$message, $queue]);
     }
     
     
     protected function _processArgumentsForWriter(array $arguments=[]) {
         $data = [];
         
         foreach ($arguments as $argument=>$value) {
             switch ( strtolower(gettype($value)) ) {
                 case 'boolean':
                     $type = AMQPAbstractCollection::T_BOOL;
                     break;
                     
                 case 'integer':
                     $type = AMQPAbstractCollection::T_INT_LONG;
                     break;
                     
                 case 'double':
                 case 'float':
                     $type = AMQPAbstractCollection::T_DECIMAL;
                     break;
                     break;
                     
                 case 'string':
                     $type = AMQPAbstractCollection::T_STRING_LONG;
                     break;
                     
                 case 'array':
                     $type = AMQPAbstractCollection::T_ARRAY;
                     break;
                     
                 case 'null':
                     $type = AMQPAbstractCollection::T_VOID;
                     break;
                     
                 default:
                     if ( $value instanceof Time ) {
                         $type = AMQPAbstractCollection::T_TIMESTAMP;
                         $value = $value->getTimestamp();
                         
                     } else {
                        throw new UnexpectedValueException();
                     }
             }
             
             $data[$argument] = [AMQPAbstractCollection::getSymbolForDataType($type), $value];
         }
         
         return $data;
     }
     
     
     public function countChannelCallbacks(array $options=[]) {
         $options = Hash::merge([
             'channel'              => null,
         ], $options);
         
         return count($this->getChannel($options['channel'])->callbacks);
     }
     
     
     public function wait(array $options=[]) {
         $options = Hash::merge([
             'allowed_methods'      => null,
             'non_blocking'         => false,
             'timeout'              => 0,
             'channel'              => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->wait($options['allowed_methods'], $options['non_blocking'], $options['timeout']);
     }
     
     
     public function qos(array $options=[]) {
         $options = Hash::merge([
             'size'                 => 0,
             'count'                => 0,
             'global'               => false,
             'channel'              => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->basic_qos($options['size'], $options['count'], $options['global']);
     }
     
     
     public function getFromQueue($queue, array $options=[]) {
         $options = Hash::merge([
             'no_ack'               => false,
             'ticket'               => null,
             'channel'              => null,
         ], $options);
         
         return $this->getChannel($options['channel'])->basic_get($queue, $options['no_ack'], $options['ticket']);
     }
     
     public function enabled() {
         return class_exists('PhpAmqpLib\Channel\AbstractChannel', true);
     }
     
     public function isQueryLoggingEnabled() {
         return false;
     }
     
     public function enableQueryLogging() {
         return false;
     }
     
     public function disableSavePoints() {
         return false;
     }
     
     public function disableQueryLogging() {
         return false;
     }
}