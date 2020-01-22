<?php

namespace MikeWeb\CakeSources\Datasource;

use MikeWeb\CakeSources\Core\DsnConfigTrait;
use Cake\Datasource\ConnectionManager as CakeConnectionManager;
use MikeWeb\CakeSources\Database\Driver\Odbc\Sqlserver;


class ConnectionManager extends CakeConnectionManager {
    
    use DsnConfigTrait;
    
    protected static function _parseDsn(string $dsn): array {
        $dsnString = $dsn;
        
        $config = static::_enhancedParseDsn($dsnString);
        
        return $config;
    }
    
    public static function bootstrap(): void {
        putenv('ODBCSYSINSTINI='.env('ODBCSYSINSTINI', '/etc/odbcinst.ini'));
        putenv('ODBCSYSINI='.env('ODBCSYSINI', '/etc/odbc.ini'));
        putenv('ODBCINI='.env('ODBCINI', null)); 
        
        $map = [
            'sqlserver-odbc'         => Sqlserver::class,
        ];
        
        if ( `uname` == 'Linux' ) {
            $map['sqlserver'] = Sqlserver::class;
        }
        
        ConnectionManager::setDsnClassMap($map);
    }
}