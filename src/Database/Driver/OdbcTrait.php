<?php

namespace MikeWeb\CakeSources\Database\Driver;

use PDO;
use Cake\Database\Driver;


trait OdbcTrait {
    
    protected function _enabled(): bool {
        if ( !in_array('odbc', PDO::getAvailableDrivers()) ) {
            return false;
        }
    }
}