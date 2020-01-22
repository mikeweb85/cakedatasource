<?php

namespace MikeWeb\CakeSources\Core;

use MikeWeb\Dsn\Dsn;
use MikeWeb\CakeSources\Core\DsnConfigTrait;
use InvalidArgumentException;


trait DsnConfigTrait {
    
    protected static function _enhancedParseDsn(string $dsn): array {
        // keep just in case
        $dsnString = $dsn;
        
        $dsn = new Dsn($dsnString);
        
        if ( !$dsn->isValid() ) {
            throw new InvalidArgumentException("The DSN string '{$dsnString}' could not be parsed.");
        }
        
        ## TODO: parse DSN
        
        
        
        
        /* if (empty($parsed['className'])) {
            $classMap = static::getDsnClassMap();
            
            $parsed['className'] = $parsed['scheme'];
            if (isset($classMap[$parsed['scheme']])) {
                $parsed['className'] = $classMap[$parsed['scheme']];
            }
        } */
    }
}