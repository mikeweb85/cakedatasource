<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Database\Driver;

use PDO;
use SplFileInfo;
use DateInterval;
use Cake\Cache\Cache;
use Cake\Utility\Hash;
use Cake\Core\Configure;
use Cake\Database\Driver;
use InvalidArgumentException;
use Cake\Core\Exception\Exception;
use Exception as PhpException;


trait OdbcTrait {

    /**
     * @param string|null $protocol
     * @param bool $reset
     * @return iterable
     * @throws PhpException
     */
    public function getOdbcDriverMap(string $protocol=null, bool $reset=false): iterable {
        $map = [];
        $cache = Cache::pool('_cake_core_');
        
        if ( !$reset ) {
            $map = $cache->get('odbc:map');
        }
        
        if ( empty($map) ) {
            $drivers = (array)$this->getOdbcDrivers($reset);
            $map = Hash::combine($drivers, '{n}.name', '{n}', '{n}.protocol');
            
            if ( !empty($map) ) {
                $ttl = 'P1D';
                
                if ( Configure::read('debug', false) ) {
                    $ttl = 'P5M';
                }
                
                $cache->set('odbc:map', $map, new DateInterval($ttl));
            }
        }
        
        if ( !empty($protocol) ) {
            return isset($map[$protocol]) ? $map[$protocol] : [];
        }
        
        return $map;
    }

    /**
     * @param bool $reset
     * @return iterable
     * @throws PhpException
     */
    public function getOdbcDriverList(bool $reset=false): iterable {
        $drivers = (array)$this->getOdbcDrivers($reset);
        
        return Hash::extract($drivers, '{n}.name');
    }

    /**
     * Returns a list of installed ODBC drivers parse from the system ODBCSYSINSTINI file
     * @param bool $reset
     * @return iterable
     * @throws PhpException
     * @throws InvalidArgumentException
     */
    public function getOdbcDrivers(bool $reset=false): iterable {
        $drivers = $matches = $driverMatch = [];
        $cache = Cache::pool('_cake_core_');
        
        if ( !$reset ) {
            $drivers = $cache->get('odbc:drivers');
        }
        
        if ( empty($drivers) ) {
            $filename = env('ODBCSYSINSTINI', '/etc/odbcinst.ini');
            
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
            }
            
            if ( !empty($drivers) ) {
                $ttl = 'P1D';
                
                if ( Configure::read('debug', false) ) {
                    $ttl = 'P5M';
                }
                
                $cache->set('odbc:drivers', $drivers, new DateInterval($ttl));
            }
        }
        
        return $drivers;
    }

    /**
     * @return bool
     */
    public function enabled(): bool {
        return in_array('odbc', PDO::getAvailableDrivers());
    }
}