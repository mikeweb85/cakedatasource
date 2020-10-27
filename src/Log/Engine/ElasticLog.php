<?php

namespace MikeWeb\CakeSources\Log\Engine;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Log\Engine\BaseLog;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\IndexRegistry;
use MikeWeb\CakeSources\Log\Engine\DatasourceLogTrait;
use Cake\Datasource\Exception\MissingDatasourceException;


class ElasticLog extends BaseLog {

    use DatasourceLogTrait;

    /**
     * @var array
     */
    protected $_additionalDefaultConfig = [
        'index'        => null,
    ];

    /**
     * DatasourceLog constructor.
     * @param array $options
     */
    public function __construct($options=[]) {
        $options += $this->_additionalDefaultConfig;
        parent::__construct($options);
    }

    protected function getRepositoryInterface(string $name): Index {
        return IndexRegistry::get($name);
    }

    protected function getNewEntity(array $data): EntityInterface {
        // $this->_repository->newD
    }
}