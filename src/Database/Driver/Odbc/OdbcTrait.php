<?php

namespace MikeWeb\CakeSources\Database\Driver;

use Cake\Database\Driver;
use PDO;


trait OdbcTrait {
    
    protected function _enabled() {
        return in_array('odbc', PDO::getAvailableDrivers());
    }
    
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
    protected function _initialize() {
        ## TODO: scan for drivers
    }
}