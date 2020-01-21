<?php

namespace App\ElasticSearch;

use Cake\ElasticSearch\Document as CakeDocument;
use Cake\ElasticSearch\IndexRegistry;
use Cake\Utility\Inflector;


class Document extends CakeDocument {
    
    public function __construct($data = [], $options = []) {
        $options['useSetters'] = true;
        
        parent::__construct($data, $options);
    }
    
    
    public function set($property, $value=null, array $options=[]) {
        if (is_string($property) && $property !== '') {
            $property = [$property=>$value];
            
        } else {
            $options = (array) $value;
        }
        
        foreach ($property as $key=>$value) {
            if ( is_array($value) ) {
                $property[$key] = $this->_createAsDocument($value);
            }
        }
        
        return parent::set($property, $options);
    }
    
    
    protected function _arrayIsNumeric(array $data) {
        if ( empty($data) ) {
            return true;
        }
        
        return ( array_keys($data) === range(0, count($data)-1) );
    }
    
    
    protected function _createAsDocument(array $data, $index=null) {
        if ( !empty($data) ) {
            if ( $this->_arrayIsNumeric($data) ) {
                foreach ($data as $i=>$entity) {
                    $data[$i] = ( is_array($entity) ) ? $this->_createAsDocument($entity, $index) : $entity;
                }
            } else {
                foreach ($data as $key=>$value) {
                    if ( is_array($value) && !$this->_arrayIsNumeric($value) ) {
                        $data[$key] = $this->_createAsDocument($value, Inflector::pluralize(Inflector::classify($key)));
                    }
                }
                
                /* $class = IndexRegistry::get($index ?: 'Default')->entityClass();
                $data = new $class($data, ['markClean'=>true, 'markNew'=>false]); */
                
                $data = (object) $data;
            }
        }
        
        return $data;
    }
}