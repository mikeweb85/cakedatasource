<?php

namespace MikeWeb\CakeSources\Cache;

use Cake\Cache\Cache as CakeCache;
use MikeWeb\CakeSources\Core\DsnConfigTrait;


class Cache extends CakeCache {
    
    use DsnConfigTrait;
    
    public static function parseDsn(string $dsn): array {
        $dsnString = $dsn;
        
        $config = static::_enhancedParseDsn($dsnString);
        
        
        return $config;
    }
    
    public static function bootstrap(): void {
        
    }
}