<?php

namespace MikeWeb\CakeSources\Cache\Engine;

use Cake\Cache\CacheEngine;
use Predis\Client;
use Predis\ClientException;
// use Predis\Cluster\Distributor\HashRing;
// use Predis\Connection\Aggregate\PredisCluster;
// use Predis\Connection\Aggregate\MasterSlaveReplication;
use Predis\Connection\ConnectionInterface;
use Predis\Connection\StreamConnection;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
// use Predis\Connection\CompositeStreamConnection;
use MikeWeb\Dsn\Dsn;
use Cake\Utility\Hash;

/**
 * Predis client for Redis storage engine
 */
class PredisEngine extends CacheEngine {
    
    /**
     * Predis wrapper.
     * @var \Predis\Client
     */
    protected $_client;
    
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
     * - `probability` Probability of hitting a cache gc cleanup. Setting to 0 will disable
     *    cache::gc from ever being called automatically.
     * - `timeout` timeout in seconds (float).
     *
     * @var array
     */
    protected $_defaultConfig = [
        'database'              => 0,
        'duration'              => 3600,
        'groups'                => [],
        'prefix'                => 'cake_predis_',
        'probability'           => 100,
        'persistent'            => false,
        'groups'                => [],
        'host'                  => null,
        'password'              => null,
        'timeout'               => 5,
        'async'                 => false,
        'read_write_timeout'    => 60,
        'iterable_multibulk'    => false,
        'throw_errors'          => true,
        'profile'               => null,
        'fallback'              => null,
    ];
    
    /**
     * Initialize the Cache Engine
     * Called automatically by the cache frontend
     * @param array $config array of setting for the engine
     * @return bool True if the engine has been successfully initialized, false if not
     */
    public function init(array $config = []) {
        // check for enhanced dsn parsing
        if ( !empty($config['dsn']) ) {
            $dsn = new Dsn($config['dsn']);
            
            if ( $dsn->isValid() ) {
                $config = Hash::merge($config, $dsn->getParameters());
                $config['host'] = $dsn->getHosts();
                $config['password'] = $dsn->getPassword();
                $config['database'] = $dsn->getDatabase();
            }
        }
        
        if ( !is_array($config['host']) ) {
            $config['host'] = [$config['host']];
        }
        
        parent::init($config);
        
        var_dump($this->_config); die();
        
        return $this->_connect();
    }
    
    /**
     * Parse hosts into configured array
     * @return array
     */
    private function _parseHosts() {
        
        return [];
    }
    
    /**
     * Connects to a Redis server
     * @return bool True if Redis server was connected
     */
    protected function _connect() {
        $connections = [];
        
        $options = [
            
        ];
        
        if ( extension_loaded('phpiredis') ) {
            $options['connections'] = [
                'tcp'           => 'Predis\Connection\PhpiredisStreamConnection',
                'unix'          => 'Predis\Connection\PhpiredisSocketConnection',
            ];
        }
        
        try {
            $connections = [];
            
            foreach ($this->_config['servers'] as $server) {
                if ( !is_array($server) ) {
                    $parts = @parse_url($server);
                    
                    $server = [
                        'async'                 => $this->_config['async'],
                        'timeout'               => $this->_config['timeout'],
                        'read_write_timeout'    => $this->_config['read_write_timeout'],
                        'weight'                => $this->_config['weight'],
                        'iterable_multibulk'    => $this->_config['iterable_multibulk'],
                        'throw_errors'          => $this->_config['throw_errors'],
                        'persistent'            => $this->_config['persistent'],
                    ];
                    
                    if ( empty($parts['scheme']) && empty($parts['host']) ) {
                        if ( empty($parts['path']) ) {
                            continue;
                        }
                        
                        if ( false !== ($socket = @realpath($parts['path'])) ) {
                            $server['scheme'] = 'unix';
                            $server['path'] = $socket;
                            
                        } else {
                            $server['scheme'] = 'tcp';
                            $server['host'] = $parts['path'];
                        }
                        
                    } else {
                        $server['scheme'] = $parts['scheme'] ?: 'tcp';
                        $server['host'] = $parts['host'];
                        $server['port'] = $parts['port'] ?: '6379';
                    }
                    
                    $queryParts = [];
                    
                    @parse_str($parts['query'], $queryParts);
                    
                    if ( !empty($queryParts['password']) ) {
                        $server['password'] = $queryParts['password'];
                        
                    } elseif ( !empty($this->_config['password']) ) {
                        $server['password'] = $this->_config['password'];
                    }
                    
                    if ( !empty($queryParts['database']) ) {
                        $server['database'] = $queryParts['database'];
                        
                    } else {
                        $server['database'] = $this->_config['database'];
                    }
                }
                
                $connections[] = $server;
            }
            
            $options = [
                'prefix'            => $this->_config['prefix'],
            ];
            
            $this->_client = new Client($connections, $options);
            $this->_client->connect();
            
            
        } catch (ClientException $e) {
            return false;
        }
        
        return $this->_client->isConnected();
    }
    
    
    /**
     * Write data for key into cache.
     *
     * @param string $key Identifier for the data
     * @param mixed $value Data to be cached
     * @return bool True if the data was successfully cached, false on failure
     */
    public function write($key, $value) {
        $key = $this->_key($key);
        
        if (!is_int($value)) {
            $value = serialize($value);
        }
        
        $duration = $this->_config['duration'];
        
        if ($duration === 0) {
            return $this->_client->set($key, $value);
        }
        
        return $this->_client->setEx($key, $duration, $value);
    }
    
    
    /**
     * Read a key from the cache
     *
     * @param string $key Identifier for the data
     * @return mixed The cached data, or false if the data doesn't exist, has expired, or if there was an error fetching it
     */
    public function read($key) {
        $key = $this->_key($key);
        
        $value = $this->_client->get($key);
        
        if (preg_match('/^[-]?\d+$/', $value)) {
            return (int)$value;
        }
        
        if ($value !== false && is_string($value)) {
            return unserialize($value);
        }
        
        return $value;
    }
    
    
    /**
     * Increments the value of an integer cached key & update the expiry time
     *
     * @param string $key Identifier for the data
     * @param int $offset How much to increment
     * @return bool|int New incremented value, false otherwise
     */
    public function increment($key, $offset = 1) {
        $duration = $this->_config['duration'];
        $key = $this->_key($key);
        
        $value = (int)$this->_client->incrBy($key, $offset);
        
        if ($duration > 0) {
            $this->_client->expire($key, $duration);
        }
        
        return $value;
    }
    
    
    /**
     * Decrements the value of an integer cached key & update the expiry time
     *
     * @param string $key Identifier for the data
     * @param int $offset How much to subtract
     * @return bool|int New decremented value, false otherwise
     */
    public function decrement($key, $offset = 1) {
        $duration = $this->_config['duration'];
        $key = $this->_key($key);
        
        $value = (int)$this->_client->decrBy($key, $offset);
        
        if ($duration > 0) {
            $this->_client->expire($key, $duration);
        }
        
        return $value;
    }
    
    
    /**
     * Delete a key from the cache
     *
     * @param string $key Identifier for the data
     * @return bool True if the value was successfully deleted, false if it didn't exist or couldn't be removed
     */
    public function delete($key) {
        $key = $this->_key($key);
        
        return $this->_client->del([$key]) > 0;
    }
    
    
    /**
     * Delete all keys from the cache
     *
     * @param bool $check If true will check expiration, otherwise delete all.
     * @return bool True if the cache was successfully cleared, false otherwise
     */
    public function clear($check) {
        if ($check) {
            return true;
        }
        
        $keys = $this->_client->getKeys($this->_config['prefix'] . '*');
        
        return $this->_client->del($keys);
    }
    
    
    /**
     * Write data for key into cache if it doesn't exist already.
     * If it already exists, it fails and returns false.
     *
     * @param string $key Identifier for the data.
     * @param mixed $value Data to be cached.
     * @return bool True if the data was successfully cached, false on failure.
     * @link https://github.com/phpredis/phpredis#setnx
     */
    public function add($key, $value) {
        $duration = $this->_config['duration'];
        $key = $this->_key($key);
        
        if (!is_int($value)) {
            $value = serialize($value);
        }
        
        // setNx() doesn't have an expiry option, so follow up with an expiry
        if ($this->_client->setNx($key, $value)) {
            return $this->_client->expire($key, $duration);
        }
        
        return false;
    }
    
    
    /**
     * Returns the `group value` for each of the configured groups
     * If the group initial value was not found, then it initializes
     * the group accordingly.
     *
     * @return array
     */
    public function groups() {
        $result = [];
        foreach ($this->_config['groups'] as $group) {
            $value = $this->_client->get($this->_config['prefix'] . $group);
            
            if (!$value) {
                $value = 1;
                $this->_client->set($this->_config['prefix'] . $group, $value);
            }
            
            $result[] = $group . $value;
        }
        
        return $result;
    }
    
    
    /**
     * Increments the group value to simulate deletion of all keys under a group
     * old values will remain in storage until they expire.
     *
     * @param string $group name of the group to be cleared
     * @return bool success
     */
    public function clearGroup($group) {
        return (bool)$this->_client->incr($this->_config['prefix'] . $group);
    }
    
    
    /**
     * Disconnects from the redis server
     */
    public function __destruct() {
        if (empty($this->_config['persistent']) && $this->_client instanceof Client) {
            $this->_client->disconnect();
        }
    }
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::get()
     */
    public function get ($key, $default = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::set()
     */
    public function set ($key, $value, $ttl = null): bool {
        // TODO Auto-generated method stub
        
    }

}
