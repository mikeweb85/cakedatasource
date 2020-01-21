<?php

namespace App\ElasticSearch;

use Cake\ElasticSearch\Query;
use Cake\Utility\Inflector;
use Cake\ElasticSearch\Index as CakeIndex;

class Index extends CakeIndex {
    
    public function initialize(array $config) {
        $this->setType( $this->getName() );
    }
    
    public function findThreaded(Query $query, array $options) {
        $options += [
            'keyField'          => Inflector::singularize($this->getName()),
            'parentField'       => 'parent.id',
            'nestingKey'        => 'children'
        ];
        
        $options = $this->_setFieldMatchers($options, ['keyField', 'parentField']);
        
        return $query->formatResults(function ($results) use ($options) {
            /** @var \Cake\Collection\CollectionInterface $results */
            return $results->nest($options['keyField'], $options['parentField'], $options['nestingKey']);
        });
    }
    
    protected function _setFieldMatchers($options, $keys) {
        foreach ($keys as $field) {
            if (!is_array($options[$field])) {
                continue;
            }
            
            if (count($options[$field]) === 1) {
                $options[$field] = current($options[$field]);
                continue;
            }
            
            $fields = $options[$field];
            $options[$field] = function ($row) use ($fields) {
                $matches = [];
                foreach ($fields as $field) {
                    $matches[] = $row[$field];
                }
                
                return implode(';', $matches);
            };
        }
        
        return $options;
    }
}