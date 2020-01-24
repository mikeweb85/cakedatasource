<?php

namespace MikeWeb\CakeSources\Database\Driver;

use PDO;
use Cake\Database\Driver;


trait OdbcTrait {
    
    /**
     *
     *
     * Sample /etc/odbcinst.ini output
     * [ODBC Driver 17 for SQL Server]
     * Description=Microsoft ODBC Driver 17 for SQL Server
     * Driver=/opt/microsoft/msodbcsql17/lib64/libmsodbcsql-17.4.so.2.1
     * UsageCount=1
     *
     */
    protected function _enabled() {
        if ( !in_array('odbc', PDO::getAvailableDrivers()) ) {
            return false;
        }
        
        
        
        
    }
}