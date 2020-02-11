<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Datasource\Predis;

use Cake\Utility\Text;
use Cake\Utility\Hash;
use Cake\Core\Exception\Exception;
use Cake\Cache\InvalidArgumentException;

use Psr\SimpleCache\CacheInterface;
use Cake\Cache\CacheEngineInterface;
use Cake\Database\TypeConverterTrait;
use Cake\Cache\CacheEngine;

use Predis\Client;
use Predis\ClientException;
use Predis\Profile\ProfileInterface;
use Predis\Collection\Iterator\Keyspace;
use Predis\Connection\PhpiredisSocketConnection;
use Predis\Connection\PhpiredisStreamConnection;
use Predis\Connection\AggregateConnectionInterface;


class PredisEngine extends CacheEngine implements CacheInterface, CacheEngineInterface {
    
    use TypeConverterTrait;
    
    protected const SEPARATOR = ':';
    
    protected const SERIALIZE_JSON = 'json';
    
    protected const SERIALIZE_PHP = 'php';
    
    /**
     * Client for Redis connection
     * @var \Predis\Client
     */
    protected $_client;
    
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
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngine::get()
     */
    public function init(array $config=[]): bool {
        if ( isset($config['auth']) ) {
            $config['password'] = $config['auth'];
            unset($config['auth']);
        }
        
        parent::init($config);
        
        if ( false === $this->connect() ) {
            ## TODO: error handling
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
                throw new Exception($message, $severity, $severity, $file, $line);
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
                $new['read_write_timeout'] = floatval($this->_config['read_write_timeout']);
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
                $connection['scheme'] == $this->_getValidScheme($this->_config['scheme']);
            }
        
        } catch (Exception $e) {
            ## TODO: exception handling
            
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
            $connections[] = $this->_buildConnectionWithParams([$this->_config['host']]);
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
                throw new Exception($message, $severity, $severity, $file, $line);
            });
            
            $this->_client = new Client($connections, $options);
            
            if ( !empty($this->_config['password']) ) {
                $this->_client->auth($this->_config['password']);
            }
            
            $this->_client->select((int)$this->_config['database']);
            
            return true;
            
        } catch(ClientException $e) {
            throw new Exception($e->getMessage());
            
        } catch (Exception $e) {
            ## TODO: handle general warning/error
            
        } finally {
            restore_error_handler();
        }
        
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get(string $key, $default=null, array $options=[]) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set(string $key, $value, $ttl=null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete(string $key) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear() {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple(array $keys, $default = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple(array $values, $ttl = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple(array $keys) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has(string $key) {
        // TODO Auto-generated method stub
        
    }
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::add()
     */
    public function add(string $key, $value): bool {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::increment()
     */
    public function increment(string $key, int $offset = 1) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::decrement()
     */
    public function decrement(string $key, int $offset = 1) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::clearGroup()
     */
    public function clearGroup(\Cake\Cache\string $group): bool {
        // TODO Auto-generated method stub
        
    }


    
}