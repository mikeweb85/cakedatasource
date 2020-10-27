<?php

namespace MikeWeb\CakeSources\Log\Engine;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Log\Engine\BaseLog;
use InvalidArgumentException;
use MikeWeb\CakeSources\Log\Engine\DatasourceLogTrait;
use Cake\Datasource\Exception\MissingDatasourceException;


class DatabaseLog extends BaseLog {

    use DatasourceLogTrait;

    /**
     * @var array
     */
    protected $_additionalDefaultConfig = [
        'model'        => null,
    ];

    /**
     * DatasourceLog constructor.
     * @param array $options
     */
    public function __construct($options=[]) {
        $options += $this->_additionalDefaultConfig;
        parent::__construct($options);
    }

    protected function getRepositoryInterface(string $name): Table {
        return TableRegistry::getTableLocator()->get($name);
    }

    protected function getNewEntity(array $data): EntityInterface {
        return $this->_repository->newEntity($data);
    }
}