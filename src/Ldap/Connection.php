<?php

namespace App\Ldap;

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
use MikeWeb\Dsn\Dsn as DSN;

class Connection implements ConnectionInterface {
    
    use InstanceConfigTrait;
    
    /**
     * Debug mode
     * @var bool
     */
    protected $_debug = false;
    
    /**
     * LDAP Connection
     * @var resource
     */
    protected $_driver;
    
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
     * Is this connected
     * @var boolean
     */
    protected $_connected = false;
    
    /**
     * Default configuration for instance
     * @var array
     */
    protected $_defaultConfig = [
        'username'              => 'guest',
        'password'              => 'guest',
        'domain'                => 'domain.local',
        'baseDN'                => 'DC=domain,DC=local',
        'search'                => 'userprincipalName',
        'query'                 => null,
        'fields'                => ['cn', 'name', 'sn', 'distinguishedname', 'department', 'displayname',
            'telephonenumber', 'telephoneNumber', 'homePhone', 'mobile', 'primarygroupid',
            'title', 'givenname', 'whencreated', 'whenchanged', 'memberof', 'company',
            'mailnickname', 'userprincipalname', 'mail', 'manager', 'description'],
        'scheme'                => 'ldap',
        'host'                  => 'localhost',
        'port'                  => null,
        'mode'                  => 'r',
        'timeout'               => 5,
        'version'               => 3,
        // 'options'               => [LDAP_OPT_NETWORK_TIMEOUT=>5, LDAP_OPT_PROTOCOL_VERSION=>3],
        'log'                   => null,
        'debug'                 => false,
        'cache'                 => 'default',
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
    public static function setInstance(Connection $connection) {
        $domain = $connection->getDomain();
        
        if ( isset(self::$_instances[$domain]) && !empty(self::$_instances[$domain]) ) {
            throw new Exception("Connection instance for vhost [{$domain}] already set.");
        }
    
        self::$_instances[$domain] = $connection;
    }
    
    /**
     *
     * @param string $vhost
     * @return Connection
     */
    public static function getInstance($domain) {
        return self::$_instances[$domain] ?: null;
    }
    
    public function getDomain() {
        return $this->_config['domain'];
    }
    
    public function __construct(array $config) {
        if ( empty($config['debug']) ) {
            $config['debug'] = Configure::read('debug', false);
        }
        
        if ( !empty($config['dsn']) ) {
            $dsn = new DSN($config['dsn']);
            
            if ( $dsn->isValid() ) {
                $config['host'] = $dsn->getHosts();
                $config['domain'] = $dsn->getDatabase();
                $config['scheme'] = $dsn->getProtocol();
                
                $auth = $dsn->getAuthentication();
                
                if ( !empty($auth) ) {
                    $config['username'] = $auth['username'];
                    
                    if ( !empty($auth['password']) ) {
                        $config['password'] = $auth['password'];
                    }
                }
                
                $config = Hash::merge($config, $dsn->getParameters());
                unset($config['port']);
            }
        }

        if ( empty($config['port']) ) {
            $config['port'] = ($config['scheme'] == 'ldaps') ? 636 : 389; 
        }
        
        if ( empty($config['searchSuffix']) ) {
            
        }
        
        $this->setConfig($config);
        
        $this->_debug = $this->_config['debug'];
        
        self::setInstance($this, $this->_config['domain']);
    }
    
    
    public function connect() {
        if ( !$this->_connected && false !== ($connection = $this->_createConnection()) ) {
            $this->_driver = $connection;
            $this->_connected = true;
        }
        
        return $this->_connected;
    }
    
    protected function _createConnection() {
        $hosts = is_array($this->_config['host']) ? $this->_config['host'] : ['host'=>$this->_config['host'], 'port'=>$this->_config['port']];
        
        shuffle($hosts);
        
        $options = [
            LDAP_OPT_NETWORK_TIMEOUT    => $this->_config['timeout'],
            LDAP_OPT_PROTOCOL_VERSION   => $this->_config['version'],
        ];
        
        foreach ($hosts as $host) {
            try {
                $port = !empty($host['port']) ? $host['port'] : $this->_config['port'];
                $connection = ldap_connect("{$this->_config['scheme']}://{$host['host']}:{$port}/");
                
                if ( false === $this->_authenticate($connection, $this->_config['username'], $this->_config['password']) ) {
                    if ( ldap_errno($connection) == 49 ) {
                        Log::critical("Invalid credentials for user [{$this->_config['username']} connecting to host [{$host}].");
                        
                        return false;
                    }
                    
                    $error = ldap_error($connection);
                    Log::error("Eror [{$error}] connecting to host [{$host['host']}].");
                    
                    continue;
                }
                
                foreach ($options as $option=>$value) {
                    ldap_set_option($connection, $option, $value);
                }
                
                return $connection;
                
            } catch (ErrorException $e) {
                $error = $e->getMessage();
                Log::error("Eror [{$error}] connecting to host [{$host['host']}].");
            }
        }
        
        return false;
    }
    
    
    public function findUser($dn, $cacheable=false) {
        if ( false === $this->connect() ) {
            return null;
        }
        
        set_error_handler(function ($errorNumber, $errorText, $errorFile, $errorLine) {
            throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
        }, E_ALL);
        
        if ( !empty($this->_config['query']) ) {
            $query = sprintf($this->_config['query'], $dn);
            
        } else {
            $query = $dn;
        }
        
        $ldapUser = null;
        $ldapCacheKey = 'ldap_query_'. hash('md5', $query);
        
        if ( !$cacheable || false === ($ldapUser = Cache::read($ldapCacheKey, $this->_config['cache'])) ) {
            try {
                $searchResults = ldap_search($this->_driver, $this->_config['baseDN'], "({$this->_config['search']}={$query})", $this->_config['fields']);
                $entry = ldap_first_entry($this->_driver, $searchResults);
                $rawLdapAttributes = ldap_get_attributes($this->_driver, $entry);
                
            } catch (ErrorException $e) {
                // Do nothing
            }
            
            if ( !empty($rawLdapAttributes) ) {
                $ldapUser = [];
                
                foreach (Hash::extract($rawLdapAttributes, '{n}') as $field) {
                    if ( empty($rawLdapAttributes[$field]) ) {
                        continue;
                    }
                    
                    if ( isset($rawLdapAttributes[$field]['count']) ) {
                        unset($rawLdapAttributes[$field]['count']);
                    }
                    
                    $ldapUser[mb_strtolower($field)] = ( count($rawLdapAttributes[$field]) > 1 ) ? $rawLdapAttributes[$field] : $rawLdapAttributes[$field][0];
                }
                
                unset($rawLdapAttributes, $entry, $searchResults);
                
                Cache::write($ldapCacheKey, $ldapUser, $this->_config['cache']);
            }
        }
        
        restore_error_handler();
        
        return $ldapUser ?: null;
    }
    
    
    public function authenticate($dn, $password) {
        if ( false !== $this->connect() ) {
            return $this->_authenticate($this->_driver, $dn, $password);
        }
        
        return false;
    }
    
    
    protected function _authenticate($connection, $dn, $password) {
        return @ldap_bind($connection, $dn, $password);
    }
    
    
    public function disconnect() {
        set_error_handler(function ($errorNumber, $errorText, $errorFile, $errorLine) {
            throw new ErrorException($errorText, 0, $errorNumber, $errorFile, $errorLine);
        }, E_ALL);
        
        if ( !empty($this->_driver) ) {
            try {
                @ldap_unbind($this->_driver);
                
            } catch (ErrorException $e) {
                // do nothing
            }
        }
        
        restore_error_handler();
        
        $this->_connected = false;
        
        return $this;
    }
    
    
    public function isConnected() {
        return $this->_connected;
    }
    
    
    public function __destruct() {
        $this->disconnect();
    }
    
    
    public function configName() {
        if (empty($this->_config['name'])) {
            return '';
        }
        
        return $this->_config['name'];
    }
    
    
    public function transactional(callable $transaction) {
        throw new NotImplementedException();
    }
    
    
    public function disableConstraints(callable $operation) {
        throw new NotImplementedException();
    }
    
    
    public function logQueries($enable=null) {
        if ($enable === null) {
            return $this->_logQueries;
        }
        
        $this->_logQueries = $enable;
    }
    
    
    public function logger($instance=null) {
        deprecationWarning('Connection::logger() is deprecated. Use Connection::setLogger()/getLogger() instead.');
        
        if ($instance === null) {
            return $this->getLogger();
        }
        
        $this->setLogger($instance);
    }
    
    
    public function getLogger() {
        if ($this->_logger === null) {
            $this->_logger = new QueryLogger();
        }
        
        return $this->_logger;
    }
    
    
    public function setLogger($logger) {
        $this->_logger = $logger;
        
        return $this;
    }
    
    
    public function supportsDynamicConstraints() {
        return false;
    }
    
    
    public function getSchemaCollection() {
        throw new NotImplementedException();
    }
    
    
    public function newQuery() {
        throw new NotImplementedException();
    }
    
    
    public function prepare($sql) {
        throw new NotImplementedException();
    }
    
    
    public function execute($query, $params=[], array $types=[]) {
        throw new NotImplementedException();
    }
    
    
    public function quote($value, $type=null) {
        throw new NotImplementedException();
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