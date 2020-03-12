<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Cache\Engine;

use Cake\Log\Log;
use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Cake\Cache\InvalidArgumentException;

use Psr\SimpleCache\CacheInterface;
use Cake\Cache\CacheEngineInterface;
// use Cake\Database\TypeConverterTrait;
use Cake\Cache\CacheEngine;

use Predis\Client;
use Predis\ClientException;
use Predis\Response\Status;
use Predis\Profile\ProfileInterface;
use Predis\Collection\Iterator\Keyspace;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
use Predis\Connection\AggregateConnectionInterface;

use DateInterval;
use Exception as BaseException;


class PredisEngine extends CacheEngine implements CacheEngineInterface, CacheInterface {
    
    ## TODO: implement schema/entity object hashing and data type conversions
    // use TypeConverterTrait;
    
    protected const SEPARATOR = ':';
    
    protected const HASH_SEPARATOR = '::';
    
    protected const SERIALIZE_JSON = 'json';
    
    protected const SERIALIZE_PHP = 'php';
    
    protected const STATUS_SUCCESS = 'OK';
    
    protected const CHECK_KEY = 'key';
    
    protected const CHECK_VALUE = 'value';
    
    /**
     * Client for Redis connection
     * @var \Predis\Client
     */
    protected $_client;
    
    /**
     * debug status of client
     * @var bool
     */
    protected $_debug;
    
    /**
     * Protocol map for connections
     * @var array
     */
    protected $_clientSchemeMap = [
        'predis'        => 'tcp',
        'prediss'       => 'tls',
        'redis'         => 'tcp',
        'rediss'        => 'tls',
        'tcp'           => 'tcp',
        'tls'           => 'tls',
    ];
    
    /**
     * List of custom implemented commands
     * @var array
     */
    protected $_customCommands = [];
    
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
        'scheme'                => 'tcp',
        'database'              => 0,
        'duration'              => 3600,
        'groups'                => [],
        'password'              => false,
        'persistent'            => true,
        'port'                  => 6379,
        'prefix'                => 'cake:',
        'host'                  => '127.0.0.1',
        'replication'           => null,
        'service'               => null,
        'timeout'               => 5,
        'profile'               => null,
        'ssl'                   => null,
        'iterable'              => null,
        'timeout'               => 5,
        'read_write_timeout'    => null,
    ];
    
    /**
     * Ensure the validity of the given cache key.
     *
     * @param string $key Key to check.
     * @return void
     * @throws \Cake\Cache\InvalidArgumentException When the key is not valid.
     */
    protected function ensureValidKey($key): void {
        if (!is_string($key) || strlen($key) === 0) {
            throw new InvalidArgumentException('A cache key must be a non-empty string.');
        }
    }
    
    /**
     * Ensure the validity of the argument type and cache keys.
     *
     * @param iterable $iterable The iterable to check.
     * @param string $check Whether to check keys or values.
     * @return void
     * @throws \Cake\Cache\InvalidArgumentException
     */
    protected function ensureValidType($iterable, string $check = self::CHECK_VALUE): void {
        if (!is_iterable($iterable)) {
            throw new InvalidArgumentException(sprintf(
                    'A cache %s must be either an array or a Traversable.',
                    $check === self::CHECK_VALUE ? 'key set' : 'set'
                    ));
        }
        
        foreach ($iterable as $key => $value) {
            if ($check === self::CHECK_VALUE) {
                $this->ensureValidKey($value);
            } else {
                $this->ensureValidKey($key);
            }
        }
    }
    
    /**
     * Convert the various expressions of a TTL value into duration in seconds
     *
     * @param \DateInterval|int|null $ttl The TTL value of this item. If null is sent, the
     *   driver's default duration will be used.
     * @return int
     */
    protected function duration($ttl): int {
        if ($ttl === null) {
            return $this->_config['duration'];
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        if ($ttl instanceof DateInterval) {
            return (int)$ttl->format('%s');
        }
        
        throw new InvalidArgumentException('TTL values must be one of null, int, \DateInterval');
    }
    
    /**
     * Returns Predis client used for connection
     * @return \Predis\Client|false
     */
    public function getClient() {
        if ( !empty($this->_client) && $this->_client instanceof Client ) {
            return $this->_client;
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::get()
     */
    public function init(array $config=[]): bool {
        if ( isset($config['auth']) ) {
            $config['password'] = $config['auth'];
            unset($config['auth']);
        }
        
        parent::init($config);
        
        $this->_debug = Configure::read('debug');
        
        if ( false === $this->connect() ) {
            $this->__debug(new Exception('Connection failed for an unknown reason.'), [], true);
        }
        
        return true;
    }
    
    /**
     * Returns staandard scheme required for connection
     * @param string $scheme
     * @throws InvalidArgumentException
     * @return string
     */
    protected function _getValidScheme(string $scheme): string {
        if ( !isset($this->_clientSchemeMap[$scheme]) || empty($this->_clientSchemeMap[$scheme]) ) {
            throw new InvalidArgumentException();
        }
        
        return $this->_clientSchemeMap[$scheme];
    }
    
    /**
     * Normalize connection objets
     * @param array $connection
     * @return array
     */
    protected function _buildConnectionWithParams(array $connection): array {
        try {
            set_error_handler(function ($severity, $message, $file, $line) {
                throw new Exception($message, $severity);
            });
                
            $connection += [
                'iterable_multibulk'    => (bool)$this->_config['iterable'],
                'timeout'               => floatval($this->_config['timeout']),
                'persistent'            => (bool)$this->_config['persistent'],
            ];
            
            if ( !isset($connection['port']) ) {
                $connection['port'] = (int)$this->_config['port'];
            }
            
            if ( !empty($this->_config['read_write_timeout']) ) {
                $connection['read_write_timeout'] = floatval($this->_config['read_write_timeout']);
            }
            
            if ( !empty($this->_config['ssl']) ) {
                $connection += [
                    'scheme'        => 'tls',
                    'ssl'           => $this->_config['ssl'],
                ];
                
            } elseif ( isset($connection['ssl']) ) {
                $connection['scheme'] = 'tls';
                
            } elseif ( isset($connection['scheme']) ) {
                $connection['scheme'] = $this->_getValidScheme($connection['scheme']);
                
            } else {
                $connection['scheme'] = $this->_getValidScheme($this->_config['scheme']);
            }
        
        } catch (Exception $e) {
            $this->__debug($e, ['connection'=>$connection], true);
            
        } finally {
            restore_error_handler();
        }
        
        return $connection;
    }
    
    /**
     * Returns if the wrapped client is connected
     * @return bool
     */
    public function connected(): bool {
        if ( !empty($this->_client) ) {
            return $this->_client->isConnected();
        }
        
        return false;
    }
    
    /**
     * Instantiates the Predis client
     * @throws \Cake\Core\Exception
     * @return bool
     */
    protected function connect(): bool {
        $connections = $options = [];
        
        if ( extension_loaded('phpiredis') ) {
            $options['connections'] = [
                'tcp'           => 'Predis\Connection\PhpiredisStreamConnection',
                'unix'          => 'Predis\Connection\PhpiredisSocketConnection',
            ];
        }
        
        $master = false;
        
        if ( is_array($this->_config['host']) ) {
            foreach ($this->_config['host'] as $connection) {
                $connection = $this->_buildConnectionWithParams($connection);
                
                if ( isset($connection['alias']) && $connection['alias'] == 'master' ) {
                    $master = true;
                }
                
                $connections[] = $connection;
            }
            
        } else {
            $connections[] = $this->_buildConnectionWithParams(['host'=>$this->_config['host']]);
        }
        
        $options = [
            'prefix'                => $this->_config['prefix'],
            'parameters'            => [
                'database'              => (int)$this->_config['database'],
             ]
        ];
        
        if ( !empty($this->_config['password']) ) {
            $options['parameters']['password'] = $this->_config['password'];
        }
        
        if ( !empty($this->_config['profile']) ) {
            if ( !($this->_config['profile'] instanceof ProfileInterface || is_string($this->_config['profile'])) ) {
                throw new InvalidArgumentException('Invalid Redis profile.');
            }
            
            $options['profile'] = $this->_config['profile'];
        }
        
        if ( !empty($this->_config['replication']) ) {
            if ( count($connections) < 2 ) {
                throw new InvalidArgumentException('More than one server required for an aggregate connection.');
            }
            
            switch ($this->_config['replication']) {
                case true:
                case 'redis':
                    $options['replication'] = true;
                    
                    if ( !$master ) {
                        $connections[0]['alias'] = 'master';
                    }
                    
                    break; break;
                    
                case 'cluster':
                    $options['cluster'] = 'redis';
                    break;
                    
                case 'sentinel':
                    $options['replication'] = 'sentinel';
                    
                    if ( empty($this->_config['service']) || !is_string($this->_config['service']) ) {
                        throw new InvalidArgumentException('A sentinel service name is required and string type.');
                    }
                    
                    $options['service'] = $this->_config['service'];
                    break;
                    
                default:
                    if ( !($this->_config['replication'] instanceof AggregateConnectionInterface || is_callable($this->_config['replication'])) ) {
                        throw new InvalidArgumentException('Unknown or invalid replication method.');
                    }
                    
                    $options['replication'] = $this->_config['replication'];
            }
        }
        
        try {
            set_error_handler(function ($severity, $message, $file, $line) {
                throw new Exception($message, $severity);
            });
            
            $this->_client = new Client($connections, $options);
            
            return true;
            
        } catch(ClientException $e) {
            $this->__debug($e, ['connections'=>$connections, 'options'=>$options], true);
            
        } catch (Exception $e) {
            $this->__debug($e, ['connections'=>$connections, 'options'=>$options], true);
            
        } finally {
            restore_error_handler();
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::_key()
     */
    protected function _key($key): string {
        $parts = [];
        
        if ( false !== (strpos($key, self::HASH_SEPARATOR)) ) {
            list($key, $field) = explode(self::HASH_SEPARATOR, $key, 2);
            $this->ensureValidKey($field);
            $parts[1] = trim($field, self::SEPARATOR);
        }
        
        $this->ensureValidKey($key);
        
        $prefix = '';
        
        if ($this->_groupPrefix) {
            $prefix = md5(implode(self::SEPARATOR, $this->groups()));
        }
        
        $key = preg_replace('/[\s]+/', self::SEPARATOR, (string)$key);
        $parts[0] = trim($prefix . $key, self::SEPARATOR);
        
        return implode(self::HASH_SEPARATOR, $parts);
    }
    
    /**
     * @param string $key
     * @return array
     */
    protected function _hashKey(string $key) {
        $field = null;
        $key = $this->_key($key);
        
        if ( false !== (strpos($key, self::HASH_SEPARATOR)) ) {
            list($key, $field) = explode(self::HASH_SEPARATOR, $key, 2);
        }
        
        return [$key, $field];
    }
    
    /**
     * Serialize value for saving to Redis.
     *
     * This is needed instead of using Redis' in built serialization feature
     * as it creates problems incrementing/decrementing intially set integer value.
     *
     * @param mixed $value Value to serialize.
     * @return string
     * @link https://github.com/phpredis/phpredis/issues/81
     */
    protected function serialize($value): string {
        if ( is_int($value) ) {
            return (string)$value;
        }
        
        return serialize($value);
    }
    
    /**
     * Unserialize string value fetched from Redis.
     * @param string $value Value to unserialize.
     * @return mixed
     */
    protected function unserialize(string $value) {
        if ( preg_match('/^[-]?\d+$/', $value) ) {
            return (int)$value;
        }
        
        return unserialize($value);
    }
    
    protected function __debug(BaseException $e, array $context=[], bool $throw=false) {
        if ( $this->_debug ) {
            $context += [
                'code'          => $e->getCode(),
                'line'          => $e->getLine(),
                'file'          => $e->getFile(),
                'class'         => get_class($e),
            ];
            
            Log::debug($e->getMessage(), $context);
        }
        
        if ( $throw ) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get($key, $default=null) {
        list($key, $field) = $this->_hashKey($key);
        
        try {
            if ( !empty($field) && $field == '*' ) {
                $values = $this->_client->hgetall($key);
                
                foreach ($values as $k=>$val) {
                    $values[$k] = is_null($val) ? $default : $this->unserialize($val);
                }
                
                return $values;
            }
            
            if ( empty($field) ) {
                $value = $this->_client->get($key);
                
            } else {
                $value = $this->_client->hget($key, $field);
            }
            
            if ($value !== false && is_string($value)) {
                return $this->unserialize($value);
            }
            
            return $default;
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'field', 'func'), false);
            
            return $default;
        }
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set($key, $value, $ttl=null): bool {
        list($key, $field) = $this->_hashKey($key);
        $value = $this->serialize($value);
        $duration = $this->duration($ttl);
        $func = empty($field) ? 'get' : 'hget';
        
        try {
            if ( empty($field) ) {
                $result = $this->_client->set($key, $value);
                
                if ( $duration > 0) {
                    $this->_client->expire($key, $duration); 
                }
                
            } else {
                $result = $this->_client->hset($key, $field, $value);
            }
            
            if ( $result instanceof Status ) {
                return ($result->getPayload() == self::STATUS_SUCCESS);
            }
            
            return ($result > 0);
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'field', 'value', 'func', 'duration'), false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete($key): bool {
        list($key, $field) = $this->_hashKey($key);
        
        try {
            if ( empty($field) ) {
                $result = $this->_client->del([$key]);
                
            } else {
                $result = $this->_client->hdel($key, [$field]);
            }
            
            if ( $result instanceof Status ) {
                return ($result->getPayload() == self::STATUS_SUCCESS);
            }
            
            return ($result > 0);
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'field'), false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear($check=false): bool {
        $isAllDeleted = true;
        $pattern = '*';
        
        try {
            $iterator = new Keyspace($this->_client, $pattern);
            
            foreach ($iterator as $key) {
                $isDeleted = ($this->_client->del([$key]) > 0);
                $isAllDeleted = $isAllDeleted && $isDeleted;
            }
            
            return $isAllDeleted;
            
        } catch (ClientException $e) {
            $this->__debug($e, ['pattern'=>$pattern], false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple($keys, $default=null): iterable {
        $data = [];
        $this->ensureValidType($keys);
        
        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }
        
        return $data;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple($values, $ttl=null): bool {
        $duration = $this->duration($ttl);
        $this->ensureValidType($values, self::CHECK_KEY);
        
        foreach ($values as $key=>$value) {
            $success = $this->set($key, $value, $duration);
            
            if ( !$success ) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple($keys): bool {
        $dictionary = [];
        $isAllDeleted = true;
        $this->ensureValidType($keys);
        
        try {
            foreach ($keys as $key) {
                list($key, $field) = $this->_hashKey($key);
                
                if ( !empty($field) ) {
                    $isDeleted = ($this->_client->hdel($key, [$field]) > 0);
                    $isAllDeleted = $isAllDeleted && $isDeleted;
                    
                } else {
                    $dictionary[] = $key;
                }
            }
            
            $result = ($this->_client->del($dictionary) == count($dictionary));
            
            return ($isAllDeleted && $result);
        
        } catch (ClientException $e) {
            $this->__debug($e, compact('keys', 'dictionary'), false);
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has($key): bool {
        list($key, $field) = $this->_hashKey($key);
        
        try {
            if ( empty($field) ) {
                $result = $this->_client->exists($key);
                
            } else {
                $result = $this->_client->hexists($key, $field);
            }
            
            return ($result > 0);
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'field'), false);
        }
        
        return false;
    }
    
    /**
     * Set expiration on a key
     * @param  string $key
     * @param  null|int|\DateInterval $ttl
     * @return bool
     */
    public function expire(string $key, $ttl=null): bool {
        list($key,) = $this->_hashKey($key);
        $duration = $this->duration(null);
        
        if ( $duration > 0 ) {
            try {
                $result = $this->_client->expire($key, $duration);
                
                if ( $result instanceof Status ) {
                    return ($result->getPayload() == self::STATUS_SUCCESS);
                }
                
                return ($result > 0);
            
            } catch (ClientException $e) {
                $this->__debug($e, compact('key', 'duration'), false);
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::add()
     */
    public function add($key, $value): bool {
        list($key, $field) = $this->_hashKey($key);
        $value = $this->serialize($value);
        
        try {
            if ( empty($field) ) {
                $duration = $this->duration(null);
                $added = $this->_client->setnx($key, $value);
                
                if ( $duration > 0 ) {
                    $this->_client->expire($key, $duration);
                }
                
            } else {
                $added = $this->_client->hsetnx($key, $field, $value);
            }
            
            return ($added > 0);
        
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'field', 'value'), false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::increment()
     */
    public function increment($key, $offset=1) {
        list($key, $field) = $this->_hashKey($key);
        
        try {
            if ( empty($field) ) {
                $duration = $this->duration(null);
                $result = $this->_client->incrby($key, abs($offset));
                
                if ( $duration > 0 ) {
                    $this->_client->expire($key, $duration);
                }
                
            } else {
                $result = $this->_client->hincrby($key, $field, abs($offset));
            }
            
            return (int)$result;
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'offset'), false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::decrement()
     */
    public function decrement($key, $offset=1) {
        list($key, $field) = $this->_hashKey($key);
        
        try {
            if ( empty($field) ) {
                $duration = $this->duration(null);
                $result = $this->_client->decrby($key, -abs($offset));
                
                if ( $duration > 0 ) {
                    $this->_client->expire($key, $duration);
                }
                
            } else {
                $result = $this->_client->hincrby($key, $field, -abs($offset));
            }
            
            return (int)$result;
            
        } catch (ClientException $e) {
            $this->__debug($e, compact('key', 'offset'), false);
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::clearGroup()
     */
    public function clearGroup($group): bool {
        return (bool)$this->_client->incr($this->_config['prefix'] . $group);
    }
    
    /**
     * Disconnects from the redis server
     */
    public function __destruct() {
        if ( empty($this->_config['persistent']) && $this->connected() ) {
            $this->_client->disconnect();
        }
    }
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::write()
     */
    public function write($key, $value, $ttl=null) {
        return $this->set($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::read()
     */
    public function read($key) {
        return $this->get($key, false);
    }

}