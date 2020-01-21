<?php 

namespace SqlServer\Database\Driver;

use Cake\Database\Driver\Sqlserver;
use SqlServer\Database\Dialect\DblibDialectTrait;
use PDO;

class Dblib extends Sqlserver {
    
    use DblibDialectTrait;
    
    public function enabled() {
        return in_array('dblib', PDO::getAvailableDrivers());
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
        
        $dsn = 'dblib:';
        
        if ( !empty($config['tds_version']) ) {
            $dsn .= "version={$config['tds_version']};";
        }
        
        if ( !empty($config['encoding']) ) {
            $dsn .= "charset={$config['encoding']};";
        }
        
        $dsn .= "host={$config['host']};port={$config['port']};dbname={$config['database']}";
        
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