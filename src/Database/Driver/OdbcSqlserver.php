<?php

namespace MikeWeb\CakeSources\Database\Driver;

use PDO;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Dialect\SqlserverDialectTrait;
use MikeWeb\CakeSources\Database\Driver\OdbcTrait;


class OdbcSqlserver extends Sqlserver {
    
    use OdbcTrait, SqlserverDialectTrait;
    
    /**
     * {@inheritDoc}
     */
    public function enabled(): bool {
        return ( in_array('odbc', PDO::getAvailableDrivers()) );
    }
    
    /**
     * {@inheritDoc}
     */
    public function connect(): bool {
        if ($this->_connection) {
            return true;
        }
        
        $config = $this->_config;
        
        if ( empty($config['driverName']) ) {
            $config['driverName'] = 'SQL Native Client';
        }
        
        $dsn = "odbc:Driver={{$config['driverName']}};Server={$config['host']},{$config['port']};Database={$config['database']};";
        ## TODO: add extended connection params
        
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
    public function supportsDynamicConstraints(): bool {
        return false;
    }
}