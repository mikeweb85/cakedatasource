<?php

namespace SqlServer\ORM;

use Cake\ORM\Table as CakeTable;
use SqlServer\ORM\Query;

class Table extends CakeTable {
    
    public function query() {
        return new Query($this->getConnection(), $this);
    }
}