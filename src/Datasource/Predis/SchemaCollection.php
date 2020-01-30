<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Datasource\Predis;

use Cake\Database\Schema\CollectionInterface;
use Cake\Database\Schema\TableSchemaInterface;
use MikeWeb\CakeSources\Exception\NotImplementedException;


class SchemaCollection implements CollectionInterface {
    
    /**
     * {@inheritDoc}
     * @see \Cake\Database\Schema\CollectionInterface::listTables()
     */
    public function listTables(): array {
        // TODO Auto-generated method stub
    }

    /**
     * {@inheritDoc}
     * @see \Cake\Database\Schema\CollectionInterface::describe()
     */
    public function describe(string $name, array $options=[]): TableSchemaInterface {
        throw new NotImplementedException();
    }
}