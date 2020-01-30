<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace MikeWeb\CakeSources\Cache\Engine;

use Predis\Client;
use Predis\ClientException;
use Cake\Utility\Text;
use Cake\Utility\Hash;
use Cake\Core\Exception\Exception;
use Cake\Cache\CacheEngine;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
use Cake\Cache\InvalidArgumentException;
use Predis\Collection\Iterator\Keyspace;


class PredisEngine extends CacheEngine {
    
    /**
     * Predis wrapper
     * 
     * @var \Predis\Client
     */
    protected $_PredisClient;
    
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
    
    /**
     * Initialize the Cache Engine
     *
     * Called automatically by the cache frontend
     *
     * @param array $config array of setting for the engine
     * @return bool True if the engine has been successfully initialized, false if not
     */
    public function init(array $config = []): bool {
        if (!extension_loaded('redis')) {
            return false;
        }
        
        if (!empty($config['host'])) {
            $config['server'] = $config['host'];
        }
        
        parent::init($config);
        
        return $this->_connect();
    }
    
    /**
     * Connects to a Redis server
     *
     * @return bool True if Redis server was connected
     */
    protected function _connect(): bool {
        $connections = $options = [];
        
        if ( extension_loaded('phpiredis') ) {
            $options['connections'] = [
                'tcp'           => 'Predis\Connection\PhpiredisStreamConnection',
                'unix'          => 'Predis\Connection\PhpiredisSocketConnection',
            ];
        }
        
        switch (true) {
            case is_string($this->_config['host']):
                $connections[] = $this->_config['host'];
                break;
                
            case (array_keys($this->_config['host']) === range(0, count($this->_config['host'])-1)):
                foreach ($this->_config['host'] as $host) {
                    if ( is_string($host) ) {
                        $connections[] = $host;
                        
                    } elseif ( is_array($host) && (isset($host['scheme']) && isset($host['host'])) ) {
                        $connections[] = "{$host['scheme']}://{$host['host']}:{$host['port']}";
                        
                    } else {
                        throw new InvalidArgumentException();
                    }
                }
                break;
                
            case (isset($this->_config['host']['scheme']) && isset($this->_config['host']['host'])):
                $connections[] = "{$this->_config['host']['scheme']}://{$this->_config['host']['host']}:{$this->_config['host']['port']}";
                break;
                
            default:
                throw new InvalidArgumentException();
        }
        
        $options = [
            'profile'           => $this->_config['profile'],
            'prefix'            => $this->_config['prefix'],
            'exceptions'        => true,
            'persistent'        => $this->_config['persistent'],
            'cluster'           => $this->_config['cluster'],
            'replication'       => $this->_config['replication'],
            'service'           => $this->_config['service'],
            'timeout'           => $this->_config['timeout'],
            'parameters'        => [
                'database'          => (int) $this->_config['database'],
            ],
        ];
        
        try {
            $this->_PredisClient = @(new Client($connections, $options));
            
            if ( !empty($this->_config['password']) ) {
                $this->_PredisClient->auth($this->_config['password']);
            }
            
            return true;
            
        } catch (ClientException $e) {
            throw new Exception($e->getMessage());
        }
        
        return false;
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
        if (is_int($value)) {
            return (string)$value;
        }
        
        return json_encode($value);
    }
    
    /**
     * Unserialize string value fetched from Redis.
     *
     * @param string $value Value to unserialize.
     * @return mixed
     */
    protected function unserialize(string $value) {
        if (preg_match('/^[-]?\d+$/', $value)) {
            return (int)$value;
        }
        
        return json_decode($value);
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::get()
     */
    public function get($key, $default=null) {
        if ( false === ($value = $this->_PredisClient->get($key)) ) {
            return $default;
        }
        
        return $this->unserialize($value);
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::set()
     */
    public function set($key, $value, $ttl=null): bool {
        $key = $this->_key($key);
        $value = $this->serialize($value);
        $duration = $this->duration($ttl);
        
        if ($duration === 0) {
            return $this->_PredisClient->set($key, $value);
        }
        
        return $this->_PredisClient->setex($key, $duration, $value);
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::increment()
     */
    public function increment(string $key, int $offset=1) {
        $key = $this->_key($key);
        $duration = $this->_config['duration'];
        $value = (int)$this->_PredisClient->incrby($key, $offset);
        
        if ($duration > 0) {
            $this->_PredisClient->expire($key, $duration);
        }
        
        return $value;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::decrement()
     */
    public function decrement(string $key, int $offset=1) {
        $key = $this->_key($key);
        $duration = $this->_config['duration'];
        $value = (int)$this->_PredisClient->decrby($key, $offset);
        
        if ($duration > 0) {
            $this->_PredisClient->expire($key, $duration);
        }
        
        return $value;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::delete()
     */
    public function delete($key): bool {
        return $this->_PredisClient->del([$this->_key($key)]) > 0;
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::clear()
     */
    public function clear(): bool {
        $isAllDeleted = true;
        $pattern = $this->_config['prefix'] . '*';
        $iterator = new Keyspace($this->_PredisClient, $pattern);
        
        foreach ($iterator as $key) {
            $isDeleted = ($this->_PredisClient->del([$key]) > 0);
            $isAllDeleted = $isAllDeleted && $isDeleted;
        }
        
        return $isAllDeleted;
    }
    
    /**
     * Returns the `group value` for each of the configured groups
     * If the group initial value was not found, then it initializes
     * the group accordingly.
     *
     * @return string[]
     */
    public function groups(): array {
        $result = [];
        
        foreach ($this->_config['groups'] as $group) {
            $value = $this->_PredisClient->get($this->_config['prefix'] . $group);
            
            if (!$value) {
                $value = $this->serialize(1);
                $this->_PredisClient->set($this->_config['prefix'] . $group, $value);
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
    public function clearGroup(string $group): bool {
        return (bool)$this->_PredisClient->incr($this->_config['prefix'] . $group);
    }
    
    public function getEngine(): Client {
        return $this->_PredisClient;
    }
}