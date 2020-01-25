<?php

namespace MikeWeb\CakeSources\ORM\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use UnexpectedValueException;
use Cake\Cache\Cache;
use Cake\ORM\Query;
use ArrayObject;
use Cake\Database\Expression\Comparison;


class CacheableBehavior extends Behavior {
    
    protected $_defaultConfig = [
        'cacheConfig'           => 'default',
        'keys'                  => [],
    ];
    
    
    public function initialize(Array $config) {
        if ( isset($config['cacheConfig']) ) {
            if ( !is_string($config['cacheConfig']) || empty(Cache::getConfig($config['cacheConfig'])) ) {
                throw new UnexpectedValueException('Cache config should be a string identifier of a definded cache configuration.');
            }
            
            $this->setConfig('cacheConfig', $config['cacheConfig'], false);
        }
        
        if ( isset($config['keys']) ) {
            if ( !is_array($config['keys']) || empty($config['keys']) ) {
                throw new UnexpectedValueException('Keys should be an array of a definded table schema fields.');
            }
            
            $this->setConfig('keys', $config['keys'], false);
        }
        
        if ( empty($this->_config['keys']) ) {
            $this->_config['keys'][] = $this->getTable()->getPrimaryKey();
        }
    }
    
    
    public function implementedEvents() {return [
            'Model.beforeFind'      => 'buildCacheableObjects',
            'Model.afterSave'       => 'clearCachedObjects',
            'Model.afterDelete'     => 'clearCachedObjects',
        ];
    }
    
    
    public function buildCacheableObjects(Event $event, Query $query, ArrayObject $options, $primary) {
        if ( (isset($options['cacheable']) && $options['cacheable'] === false) || !$primary || !empty($query->clause('select'))) {
            return;
        }
        
        $single = ($query->clause('limit') === 1);
        
        if ( !$single ) {
            return;
        }
        
        $conditions = [];
        $table = $this->getTable();
        
        $query->clause('where')->traverse(function(Comparison $exp) use ($table, &$conditions) {
            $matches = [];
            
            if ( preg_match('/(.?(?<alias>\w+).?\.)?.?(?<field>\w+).?/', $exp->getField(), $matches) ) {
                if (empty($matches['alias']) || $matches['alias'] == $table->getAlias()) {
                    $conditions[ $matches['field'] ] = $exp->getValue();
                }
            }
        });
        
        if ( empty($conditions) || $conditions > 1 ) {
            return;
        }
        
        $keyPrefix = 'cacheable.'. $table->getRegistryAlias();
        
        foreach ($this->_config['keys'] as $cacheableKey) {
            if ( !isset($conditions[$cacheableKey]) ) {
                continue;
            }
            
            $query->cache("{$keyPrefix}.{$cacheableKey}.$conditions[$cacheableKey]", $this->_config['cacheConfig']);
        }
        
    }
    
    
    public function clearCachedObjects(Event $event, EntityInterface $entity) {
        $keyPrefix = 'cacheable.'. $this->getTable()->getRegistryAlias();
        
        foreach ($this->_config['keys'] as $cacheableKey) {
            $colValue = $entity->get($cacheableKey);
            
            if ( !empty($colValue) ) {
                Cache::delete("{$keyPrefix}.{$cacheableKey}.$colValue", $this->_config['cacheConfig']);
            }
        }
    }
}