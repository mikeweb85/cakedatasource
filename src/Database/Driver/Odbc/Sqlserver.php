<?php

namespace MikeWeb\CakeSources\Database\Driver\Odbc;

use PDO;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Dialect\SqlserverDialectTrait;
use MikeWeb\CakeSources\Database\Driver\OdbcTrait;
use MikeWeb\CakeSources\Datasource\ConnectionManager;

class Sqlserver extends Sqlserver {
    
    use OdbcTrait, SqlserverDialectTrait;
    
    public function enabled() {
        if ( !in_array('odbc', PDO::getAvailableDrivers()) ) {
            return false;
        }
        
        return ( !empty(ConnectionManager::getOdbcDriversMap('sqlserver')) );
    }
    
    /**
     * Establishes a connection to the database server
     *
     * @return bool true on success
     */
    public function connect() {
        if ($this->_connection) {
            return true;
        }
        
        $config = $this->_config;
        
        if ( empty($config['odbcDriver']) ) {
            $config['odbcDriver'] = 'SQL Native Client';
        }
        
        $dsn = "odbc:Driver={{$config['odbcDriver']}};Server={$config['host']},{$config['port']};Database={$config['database']};";
        
        $this->_connect($dsn, $config);
        
        $connection = $this->getConnection();
        
        if (!empty($config['init']))  {
            foreach ((array)$config['init'] as $command) {
                $connection->exec($command);
            }
        }
        
        if (!empty($config['settings']) && is_array($config['settings'])) {
            foreach ($config['settings'] as $key => $value) {
                $connection->exec("SET {$key} {$value}");
            }
        }
        
        return true;
    }
    
    /**
     * {@inheritDoc}
     */
    public function supportsDynamicConstraints() {
        return false;
    }
}