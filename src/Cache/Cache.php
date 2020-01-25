<?php

namespace MikeWeb\CakeSources\Cache;

use Cake\Cache\Cache as CakeCache;
use MikeWeb\CakeSources\Core\DsnConfigTrait;


class Cache extends CakeCache {
    
    use DsnConfigTrait;
    
    public static function setConfig($key, $config=null): void {
        CakeCache::setConfig($key, $config);
    }
    
    public static function getConfig(string $key) {
        return CakeCache::getConfig($key);
    }
    
    public static function get(string $name, bool $useAliases=true) {
        return CakeCache::get($name, $useAliases);
    }
    
    public static function dropAlias(string $name): void {
        CakeCache::dropAlias($name);
    }
    
    public static function alias(string $alias, string $source): void {
        CakeCache::alias($alias, $source);
    }
    
    public static function getConfigOrFail(string $key) {
        return CakeCache::getConfigOrFail($key);
    }
    
    public static function drop(string $config): bool {
        return CakeCache::drop($config);
    }
    
    public static function configured(): array {
        return CakeCache::configured();
    }
    
    public static function setDsnClassMap(array $map): void {
        CakeCache::setDsnClassMap($map);
    }
    
    public static function getDsnClassMap(): array {
        return CakeCache::getDsnClassMap();
    }
    
    public static function pool(string $config) {
        return CakeCache::pool($config);
    }
    
    public static function parseDsn(string $dsn): array {
        return static::_enhancedParseDsn($dsn);
    }
    
    public static function bootstrap(): void {
        
    }
}