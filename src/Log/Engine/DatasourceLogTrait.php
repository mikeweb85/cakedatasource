<?php

namespace MikeWeb\CakeSources\Log\Engine;

use Cake\Datasource\Exception\MissingDatasourceException;
use Cake\Datasource\RepositoryInterface;
use Cake\Event\EventDispatcherInterface;


trait DatasourceLogTrait {

    /**
     * @var RepositoryInterface|EventDispatcherInterface
     */
    protected $_repository;

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context=[]) {
        $data = $context + ['level'=>$level, 'message'=>$message];
        $event = $this->_repository->dispatchEvent('Model.beforeLog', $data, $this->_repository);

        if ( $event->isStopped() ) {
            ## TODO: throw error
        }

        $data = $event->getResult();

        if ( $data === false || empty($data) || !is_array($data) ) {
            ## TODO: throw error
        }

        $entity = $this->getNewEntity($data);

        if ( false === $this->_repository->save($entity) ) {
            ## TODO: throw error
        }
    }


    public function setRepository(RepositoryInterface $repository): void {
        if ( empty($repo) ) {
            throw new MissingDatasourceException();
        }

        if ( is_string($repo) ) {
        }
    }

    public function getRepository(): RepositoryInterface {

    }
}