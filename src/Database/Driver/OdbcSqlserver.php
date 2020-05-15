<?php

namespace MikeWeb\CakeSources\Database\Driver;

use Cake\Database\Driver\Sqlserver;
use Cake\Database\Dialect\SqlserverDialectTrait;
use MikeWeb\CakeSources\Database\Driver\OdbcTrait;
use Exception as PhpException;


class OdbcSqlserver extends Sqlserver {
    
    use OdbcTrait, SqlserverDialectTrait {
        enabled as protected _odbcEnabled;
    }

    /**
     * {@inheritDoc}
     * @throws PhpException
     */
    public function enabled(): bool {
        if ( !$this->_odbcEnabled() ) {
            return false;
        }
        
        $drivers = (array)$this->getOdbcDriverMap('sqlserver');
        
        return ( !empty($drivers) );
    }

    /**
     * {@inheritDoc}
     * @throws PhpException
     */
    public function connect(): bool {
        if ($this->_connection) {
            return true;
        }
        
        $config = $this->_config;
        
        if ( empty($config['driverName']) ) {
            $drivers = (array)$this->getOdbcDriverMap('sqlserver');
            $firstDriver = array_shift($drivers);
            $config['driverName'] = $firstDriver['name'];
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

    /**
     * @return int
     * @throws PhpException
     */
    public function _version(): int {
        $this->connect();
        return (int)$this->_connection->query("SELECT SERVERPROPERTY('ProductVersion') as VERSION")->fetchColumn();
    }
}