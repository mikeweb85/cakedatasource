<?php

namespace Dblib\ORM;

use Cake\ORM\ResultSet as CakeResultSet;
use Cake\ORM\Query;

class ResultSet extends CakeResultSet {
    
    protected function _calculateColumnMap($query) {
        parent::_calculateColumnMap($query);
        
        $fields = $query->clause('select');
        $defaultAlias = $query->getRepository()->getAlias();
        $quote = $query->getConnection()->getDriver()->isAutoQuotingEnabled();
        
        foreach ($this->_map as $repo=>$attributes) {
            foreach ($attributes as $key=>$field) {
                $index = $quote ? $query->getConnection()->getDriver()->quoteIdentifier($key) : $key;
                
                if ( strpos($key, 'rand_') === 0 && isset($fields[$index]) ) {
                    list(, $col) = explode('.', $fields[$index]);
                    $this->_map[$repo][$key] = trim($col, '"`[]');
                }
            }
        }
    }
}