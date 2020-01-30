<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Datasource\Predis;

use Psr\SimpleCache\CacheInterface;
use Cake\Cache\CacheEngineInterface;
use Cake\Database\TypeConverterTrait;
use Cake\Cache\CacheEngine;


class Client extends CacheEngine implements CacheInterface, CacheEngineInterface {
    
    use TypeConverterTrait;
    
    protected const SERIALIZE_JSON = 'json';
    
    protected const SERIALIZE_PHP = 'php';
    
    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get(string $key, $default = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set(string $key, $value, $ttl = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete(string $key) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear() {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple(array $keys, $default = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple(array $values, $ttl = null) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple(array $keys) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has(string $key) {
        // TODO Auto-generated method stub
        
    }
    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::add()
     */
    public function add(string $key, $value): bool {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::increment()
     */
    public function increment(string $key, int $offset = 1) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::decrement()
     */
    public function decrement(string $key, int $offset = 1) {
        // TODO Auto-generated method stub
        
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Cache\CacheEngineInterface::clearGroup()
     */
    public function clearGroup(\Cake\Cache\string $group): bool {
        // TODO Auto-generated method stub
        
    }


    
}