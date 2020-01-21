<?php

namespace SqlServer\ORM;

use Dblib\ORM\ResultSet;
use Cake\ORM\Query as CakeQuery;
use Cake\Datasource\QueryTrait;
use Cake\Utility\Security;
use SqlServer\Database\Driver\Dblib;
use SqlServer\Database\Driver\Odbc;
use Cake\Database\Expression\FunctionExpression;

class Query extends CakeQuery {
    
    /**
     * {@inheritDoc}
     * @see CakeQuery::__construct()
     */
    public function __construct($connection, $table) {
        if ( $connection->getDriver() instanceof Dblib ) {
            $this->_useBufferedResults = false;
        }
        
        parent::__construct($connection, $table);
    }
    
    /**
     * {@inheritDoc}
     * @see QueryTrait::aliasField()
     */
    public function aliasField($field, $alias=null) {
        $return = parent::aliasField($field, $alias);
        
        foreach ($return as $key=>$aliasedField) {
            if ( strlen($key) > 30 ) {
                unset( $return[$key] );
                
                $key = 'rand_'. Security::randomString(25);
                
                $this->getTypeMap()->addDefaults([
                    $key        => ($this->getTypeMap()->getDefaults())[$aliasedField],
                ]);
                
                $return[$key] = $aliasedField;
            }
        }
        
        return $return;
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\ORM\Query::_execute()
     */
    protected function _execute() {
        $this->triggerBeforeFind();
        
        if ($this->_results) {
            $decorator = $this->_decoratorClass();
            
            return new $decorator($this->_results);
        }
        
        $statement = $this->getEagerLoader()->loadExternal($this, $this->execute());
        
        return new ResultSet($this, $statement);
    }
    
    /**
     * {@inheritDoc}
     * @see \Cake\ORM\Query::_transformQuery()
     */
    protected function _transformQuery() {
        if ( !$this->_dirty || $this->_type !== 'select' ) {
            return;
        }
        
        parent::_transformQuery();
        
        $map = $this->getSelectTypeMap()->getDefaults();
        $quote = $this->getConnection()->getDriver()->isAutoQuotingEnabled();
        
        foreach ($this->_parts['select'] as $alias=>$aliasedField) {
            if ( !isset($map[$alias]) ) {
                continue;
            }
            
            if ( $quote ) {
                $aliasedField = $this->getConnection()->getDriver()->quoteIdentifier($aliasedField);
            }
            
            switch ( $map[$alias] ) {
                case 'nchar':
                    $this->_parts['select'][$alias] = new FunctionExpression('CONVERT', ['char(max)'=>'literal', $aliasedField]);
                    break;
                    
                case 'nvarchar':
                case 'nstring':
                    $this->_parts['select'][$alias] = new FunctionExpression('CONVERT', ['varchar(max)'=>'literal', $aliasedField]);
                    break;
            }
        }
    }
}