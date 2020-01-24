<?php

namespace MikeWeb\CakeSources\Datasource;

use SplFileInfo;
use InvalidArgumentException;
use MikeWeb\CakeSources\Cache\Cache;
use MikeWeb\CakeSources\Core\DsnConfigTrait;
use Cake\Datasource\ConnectionManager as CakeConnectionManager;
use MikeWeb\CakeSources\Database\Driver\Odbc\Sqlserver;
use Cake\Utility\Hash;


class ConnectionManager extends CakeConnectionManager {
    
    use DsnConfigTrait;
    
    protected static $_odbcClassMap;
    
    protected static function _parseDsn(string $dsn): array {
        $dsnString = $dsn;
        
        $config = static::_enhancedParseDsn($dsnString);
        
        return $config;
    }
    
    public static function bootstrap(): void {
        putenv('ODBCSYSINSTINI='.env('ODBCSYSINSTINI', '/etc/odbcinst.ini'));
        putenv('ODBCSYSINI='.env('ODBCSYSINI', '/etc/odbc.ini'));
        putenv('ODBCINI='.env('ODBCINI', null)); 
        
        $map = [];
        $driverMap = static::getOdbcDriversMap();
        
        if ( array_key_exists('sqlserver', $driverMap) ) {
            $map['sqlserver-odbc'] = Sqlserver::class;
            
            if ( trim(`uname`) == 'Linux' ) {
                $map['sqlserver'] = Sqlserver::class;
            }
        }
        
        ConnectionManager::setDsnClassMap($map);
    }
    
    public static function getOdbcDriversMap(string $protocol=null): array {
        if ( null === (static::$_odbcClassMap = Cache::read('odbc_drivers', '_cake_core_')) ) {
            $filename = getenv('ODBCSYSINSTINI');
            
            if ( empty($filename) ) {
                throw new InvalidArgumentException("No ODBCINST file defined.");
            }
            
            $odbcInst = new SplFileInfo($filename);
            
            if ( !($odbcInst->isFile() && $odbcInst->isReadable()) ) {
                throw new InvalidArgumentException("ODBCINST file [{$filename}] does not exist or is not readable.");
            }
            
            if ( false === ($contents = @file_get_contents($filename, false)) ) {
                throw new InvalidArgumentException("ODBCINST file [{$filename}] could not be loaded.");
            }
            
            $drivers = [];
            
            if ( preg_match_all('/(!\n)?(\[(?P<name>.*)\])\r?\n(Description=)(?P<description>.*)/im', $contents, $matches) ) {
                foreach ($matches['name'] as $i=>$match) {
                    $driver = [
                        'name'          => $matches['name'][$i],
                        'description'   => $matches['description'][$i],
                    ];
                    
                    switch (true) {
                        case ( 0 < preg_match('/(SQL\sNative\sClient|ODBC\sDriver\s(?P<version>[\.0-9]+)\sfor\sSQL\sServer)/i', $driver['name'], $driverMatch) ):
                            if ( !empty($driverMatch['version']) ) {
                                $driver['version'] = $driverMatch['version'];
                            }
                            
                            $driver['protocol'] = 'sqlserver';
                            break;
                            
                        case ( 0 < preg_match('/MySQL/i', $driver['name']) ):
                            $driver['protocol'] = 'mysql';
                            break;
                            
                        case ( 0 < preg_match('/PostgreSQL/i', $driver['name']) ):
                            $driver['protocol'] = 'postgres';
                            break;
                            
                        default:
                            continue 2;
                    }
                    
                    $drivers[] = $driver;
                }
                
                if ( !empty($drivers) ) {
                    static::$_odbcClassMap = Hash::combine($drivers, '{n}.name', '{n}', '{n}.protocol');
                }
            }
            
            if ( !empty(static::$_odbcClassMap) ) {
                Cache::write('odbc_drivers', static::$_odbcClassMap, '_cake_core_');
            }
        }
        
        if ( !empty($protocol) ) {
            return ( !empty(static::$_odbcClassMap[$protocol]) ) ? static::$_odbcClassMap[$protocol] : [];
        }
        
        return static::$_odbcClassMap;
    }
}