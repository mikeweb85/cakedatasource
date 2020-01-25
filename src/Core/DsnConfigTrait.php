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
        
        var_dump($dsn); die();
        
        
        /* $exists = [];
        foreach ($parsed as $k => $v) {
            if (is_int($k)) {
                unset($parsed[$k]);
            } elseif (strpos($k, '_') === 0) {
                $exists[substr($k, 1)] = ($v !== '');
                unset($parsed[$k]);
            } elseif ($v === '' && !$exists[$k]) {
                unset($parsed[$k]);
            }
        }
        
        $query = '';
        
        if (isset($parsed['query'])) {
            $query = $parsed['query'];
            unset($parsed['query']);
        }
        
        parse_str($query, $queryArgs);
        
        foreach ($queryArgs as $key => $value) {
            if ($value === 'true') {
                $queryArgs[$key] = true;
            } elseif ($value === 'false') {
                $queryArgs[$key] = false;
            } elseif ($value === 'null') {
                $queryArgs[$key] = null;
            }
        }
        
        $parsed = $queryArgs + $parsed;
        
        if (empty($parsed['className'])) {
            $classMap = static::getDsnClassMap();
            
            $parsed['className'] = $parsed['scheme'];
            if (isset($classMap[$parsed['scheme']])) {
                $parsed['className'] = $classMap[$parsed['scheme']];
            }
        } */
        
        ## TODO: parse DSN
        
        
        var_dump($dsn);
        
        /* if (empty($parsed['className'])) {
            $classMap = static::getDsnClassMap();
            
            $parsed['className'] = $parsed['scheme'];
            if (isset($classMap[$parsed['scheme']])) {
                $parsed['className'] = $classMap[$parsed['scheme']];
            }
        } */
    }
}