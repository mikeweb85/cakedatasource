<?php

namespace SqlServer\Database\Dialect;

use SqlServer\Database\Schema\DblibSchema;

trait DblibDialectTrait {
    
    /**
     * Get the version of SQLserver we are connected to.
     *
     * @return int
     */
    public function _version() {
        $this->connect();
        return $this->_connection->query("SELECT SERVERPROPERTY('productversion') as VERSION")->fetchColumn();
    }
    
    /**
     * Get the schema dialect.
     *
     * Used by Cake\Schema package to reflect schema and
     * generate schema.
     *
     * @return \App\Database\Schema\DblibSchema
     */
    public function schemaDialect() {
        return new DblibSchema($this);
    }
}

