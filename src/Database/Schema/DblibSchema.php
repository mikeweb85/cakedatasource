<?php

namespace SqlServer\Database\Schema;

use Cake\Database\Schema\SqlserverSchema;

class DblibSchema extends SqlserverSchema {
    
    public function describeColumnSql($tableName, $config) {
        $sql = 'SELECT DISTINCT
            AC.column_id AS [column_id],
            AC.name AS [name],
            TY.name AS [type],
            AC.max_length AS [char_length],
            AC.precision AS [precision],
            AC.scale AS [scale],
            AC.is_identity AS [autoincrement],
            AC.is_nullable AS [null],
            AC.collation_name AS [collation_name],
            NULL as [default]
            FROM sys.[objects] T
            INNER JOIN sys.[schemas] S ON S.[schema_id] = T.[schema_id]
            INNER JOIN sys.[all_columns] AC ON T.[object_id] = AC.[object_id]
            INNER JOIN sys.[types] TY ON TY.[user_type_id] = AC.[user_type_id]
            WHERE T.[name] = ? AND S.[name] = ?
            ORDER BY column_id';
        
        $schema = empty($config['schema']) ? static::DEFAULT_SCHEMA_NAME : $config['schema'];
        
        return [$sql, [$tableName, $schema]];
    }
}