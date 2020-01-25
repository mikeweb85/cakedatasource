<?php

namespace MikeWeb\CakeSources\Datasource;


use Cake\Datasource\Paginator as CakePaginator;
use Cake\Datasource\RepositoryInterface;
use Cake\ElasticSearch\Index;


class Paginator extends CakePaginator {
    
    /**
     * Prefixes the field with the table alias if possible.
     *
     * @param \Cake\Datasource\RepositoryInterface $object Repository object.
     * @param array $order Order array.
     * @param bool $whitelisted Whether or not the field was whitelisted.
     * @return array Final order array.
     */
    protected function _prefix(RepositoryInterface $object, $order, $whitelisted=false): array {
        $disableAliasing = false;
        
        if ( $object instanceof Index ) {
            $disableAliasing = true;
        }
        
        $tableAlias = $object->getAlias();
        $tableOrder = [];
        foreach ($order as $key => $value) {
            if (is_numeric($key)) {
                $tableOrder[] = $value;
                continue;
            }
            $field = $key;
            $alias = $tableAlias;
            
            if (strpos($key, '.') !== false) {
                list($alias, $field) = explode('.', $key);
            }
            $correctAlias = ($tableAlias === $alias);
            
            if ($correctAlias && $whitelisted) {
                // Disambiguate fields in schema. As id is quite common.
                if ($object->hasField($field)) {
                    $field = ($disableAliasing) ? $field : "{$alias}.{$field}";
                }
                $tableOrder[$field] = $value;
            } elseif ($correctAlias && $object->hasField($field)) {
                $key = ($disableAliasing) ? $field : "{$tableAlias}.{$field}";
                $tableOrder[$key] = $value;
            } elseif (!$correctAlias && $whitelisted) {
                $key = ($disableAliasing) ? $field : "{$alias}.{$field}";
                $tableOrder[$key] = $value;
            }
        }
        
        return $tableOrder;
    }
}