<?php

namespace MikeWeb\CakeSources\Ldap;

use Cake\Datasource\QueryInterface;
use Cake\Datasource\QueryTrait;


/**
 * @method $this andWhere($conditions, $types = [])
 * @method $this select($fields = [], $overwrite = false)
 */
class Query implements IteratorAggregate, QueryInterface {

    use QueryTrait;

    /**
     * Indicates that the operation should append to the list
     *
     * @var integer
     */
    const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var integer
     */
    const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var boolean
     */
    const OVERWRITE = true;

    /**
     * The Elastica Query object that is to be executed after
     * being built.
     *
     * @var \Elastica\Query
     */
    protected $_elasticQuery;

    /**
     * The various query builder parts that will
     * be transferred to the elastica query.
     *
     * @var array
     */
    protected $_queryParts = [
        'fields' => [],
        'limit' => null,
        'offset' => null,
        'order' => [],
        'highlight' => null,
        'aggregations' => [],
        'query' => null,
        'filter' => null,
        'postFilter' => null,
    ];

    /**
     * Internal state to track whether or not the query has been modified.
     *
     * @var bool
     */
    protected $_dirty = false;

    /**
     * Additional options for Elastica\Type::search()
     *
     * @see Elastica\Search::OPTION_SEARCH_* constants
     * @var array
     */
    protected $_searchOptions = [];

    /**
     * Query constructor
     *
     * @param \Cake\ElasticSearch\Index $repository The type of document.
     */
    public function __construct(Index $repository) {
        $this->repository($repository);
        //$this->_elasticQuery = new ElasticaQuery;
    }


    /**
     * @inheritDoc
     */
    public function find($finder, array $options = []) {
        // TODO: Implement find() method.
    }

    /**
     * @inheritDoc
     */
    public function count() {
        // TODO: Implement count() method.
    }

    /**
     * @inheritDoc
     */
    public function limit($num) {
        // TODO: Implement limit() method.
    }

    /**
     * @inheritDoc
     */
    public function offset($num) {
        // TODO: Implement offset() method.
    }

    /**
     * @inheritDoc
     */
    public function order($fields, $overwrite = false) {
        // TODO: Implement order() method.
    }

    /**
     * @inheritDoc
     */
    public function page($num, $limit = null) {
        // TODO: Implement page() method.
    }

    /**
     * @inheritDoc
     */
    public function where($conditions = null, $types = [], $overwrite = false) {
        // TODO: Implement where() method.
    }

    /**
     * @inheritDoc
     */
    public function applyOptions(array $options) {
        // TODO: Implement applyOptions() method.
    }

    /**
     * @inheritDoc
     */
    protected function _execute() {
        // TODO: Implement _execute() method.
    }

    public function __call($name, $arguments) {
        // TODO: Implement @method $this andWhere($conditions, $types = [])
        // TODO: Implement @method $this select($fields = [], $overwrite = false)
    }
}
