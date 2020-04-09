<?php

namespace MikeWeb\CakeSources\Ldap;

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\SchemaInterface;
use Cake\ElasticSearch\Datasource\SchemaCollection;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use BadMethodCallException;
use Cake\ORM\Table;
use MikeWeb\CakeSources\Ldap\Query;
use Cake\Utility\Inflector;
use MikeWeb\CakeSources\Exception\NotImplementedException;


class Container implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface {

    use EventDispatcherTrait;

    /**
     * Connection instance
     *
     * @var Connection
     */
    protected $_connection;

    /**
     * The name of the Elasticsearch index this class represents
     *
     * @var string
     */
    protected $_name;

    /**
     * The name of the Elasticsearch mapping type which this class represents
     *
     * For default, the mapping type is equal to index name for easy use.
     *
     * @var string
     */
    protected $_type;

    /**
     * Registry key used to create this index object
     *
     * @var string
     */
    protected $_registryAlias;

    /**
     * The name of the class that represent a single document for this type
     *
     * @var string
     */
    protected $_documentClass;

    /**
     * Collection of Embedded sub documents this type has.
     *
     * @var array
     */
    protected $embeds = [];

    /**
     * The mapping schema for this type.
     *
     * @var SchemaCollection
     */
    protected $schema;

    /**
     * Get the default connection name.
     *
     * This method is used to get the fallback connection name if an
     * instance is created through the TableLocator without a connection.
     *
     * @return string
     * @see \Cake\ORM\Locator\TableLocator::get()
     */
    public static function defaultConnectionName() {
        return 'ldap';
    }

    /**
     * Get the Model callbacks this table is interested in.
     *
     * By implementing the conventional methods a table class is assumed
     * to be interested in the related event.
     *
     * Override this method if you need to add non-conventional event listeners.
     * Or if you want you table to listen to non-standard events.
     *
     * The conventional method map is:
     *
     * - Model.beforeMarshal => beforeMarshal
     * - Model.buildValidator => buildValidator
     * - Model.beforeFind => beforeFind
     * - Model.beforeSave => beforeSave
     * - Model.afterSave => afterSave
     * - Model.afterSaveCommit => afterSaveCommit
     * - Model.beforeDelete => beforeDelete
     * - Model.afterDelete => afterDelete
     * - Model.afterDeleteCommit => afterDeleteCommit
     * - Model.beforeRules => beforeRules
     * - Model.afterRules => afterRules
     *
     * @return array
     */
    public function implementedEvents() {
        $eventMap = [
            'Model.beforeMarshal'       => 'beforeMarshal',
            'Model.buildValidator'      => 'buildValidator',
            'Model.beforeFind'          => 'beforeFind',
            'Model.beforeSave'          => 'beforeSave',
            'Model.afterSave'           => 'afterSave',
            'Model.afterSaveCommit'     => 'afterSaveCommit',
            'Model.beforeDelete'        => 'beforeDelete',
            'Model.afterDelete'         => 'afterDelete',
            'Model.afterDeleteCommit'   => 'afterDeleteCommit',
            'Model.beforeRules'         => 'beforeRules',
            'Model.afterRules'          => 'afterRules',
        ];

        $events = [];

        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }

            $events[$event] = $method;
        }

        return $events;
    }

    /**
     * Initialize a table instance. Called after the constructor.
     *
     * You can use this method to define associations, attach behaviors
     * define validation and do any other initialization logic you need.
     *
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->belongsTo('Users');
     *      $this->belongsToMany('Tagging.Tags');
     *      $this->setPrimaryKey('something_else');
     *  }
     * ```
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config) {
    }

    /**
     * Returns the connection instance.
     *
     * @return Connection
     */
    public function getConnection() {
        return $this->_connection;
    }

    /**
     * Returns the schema table object describing this table's properties.
     *
     * @return \Cake\Database\Schema\TableSchema
     */
    public function getSchema()
    {
        if ($this->_schema === null) {
            $this->_schema = $this->_initializeSchema(
                $this->getConnection()
                    ->getSchemaCollection()
                    ->describe($this->getTable())
            );
        }

        return $this->_schema;
    }

    /**
     * Sets the schema table object describing this table's properties.
     *
     * If an array is passed, a new TableSchema will be constructed
     * out of it and used as the schema for this table.
     *
     * @param array|SchemaInterface $schema Schema to be used for this table
     * @return $this
     */
    public function setSchema($schema) {
        if (is_array($schema)) {
            $constraints = [];

            if (isset($schema['_constraints'])) {
                $constraints = $schema['_constraints'];
                unset($schema['_constraints']);
            }

            $schema = new TableSchema($this->getTable(), $schema);

            foreach ($constraints as $name => $value) {
                $schema->addConstraint($name, $value);
            }
        }

        $this->_schema = $schema;

        return $this;
    }

    /**
     * Returns the schema table object describing this table's properties.
     *
     * If a TableSchema is passed, it will be used for this table
     * instead of the default one.
     *
     * If an array is passed, a new TableSchema will be constructed
     * out of it and used as the schema for this table.
     *
     * @param array|SchemaInterface|null $schema New schema to be used for this table
     * @return SchemaInterface
     * @deprecated 3.4.0 Use setSchema()/getSchema() instead.
     */
    public function schema($schema = null) {
        deprecationWarning(
            get_called_class() . '::schema() is deprecated. ' .
            'Use setSchema()/getSchema() instead.'
        );

        if ($schema !== null) {
            $this->setSchema($schema);
        }

        return $this->getSchema();
    }

    /**
     * {@inheritDoc}
     * @deprecated 3.4.0 Use setAlias()/getAlias() instead.
     */
    public function alias($alias = null) {
        deprecationWarning(
            get_called_class() . '::alias() is deprecated. ' .
            'Use setAlias()/getAlias() instead.'
        );

        if ($alias !== null) {
            $this->setAlias($alias);
        }

        return $this->getAlias();
    }

    /**
     * @inheritDoc
     */
    public function hasField($field) {
        return $this->schema()->field($field) !== null;
    }

    /**
     * @inheritDoc
     */
    public function find($type = 'all', $options = []) {
        $query = $this->query();
        $query->select();

        return $this->callFinder($type, $query, $options);
    }

    /**
     * @inheritDoc
     */
    public function get($primaryKey, $options=[]) {
        $key = (array)$this->getPrimaryKey();
        $alias = $this->getAlias();
        foreach ($key as $index => $keyname) {
            $key[$index] = $alias . '.' . $keyname;
        }
        $primaryKey = (array)$primaryKey;
        if (count($key) !== count($primaryKey)) {
            $primaryKey = $primaryKey ?: [null];
            $primaryKey = array_map(function ($key) {
                return var_export($key, true);
            }, $primaryKey);

            throw new InvalidPrimaryKeyException(sprintf(
                'Record not found in table "%s" with primary key [%s]',
                $this->getTable(),
                implode(', ', $primaryKey)
            ));
        }
        $conditions = array_combine($key, $primaryKey);

        $cacheConfig = isset($options['cache']) ? $options['cache'] : false;
        $cacheKey = isset($options['key']) ? $options['key'] : false;
        $finder = isset($options['finder']) ? $options['finder'] : 'all';
        unset($options['key'], $options['cache'], $options['finder']);

        $query = $this->find($finder, $options)->where($conditions);

        if ($cacheConfig) {
            if (!$cacheKey) {
                $cacheKey = sprintf(
                    'get:%s.%s%s',
                    $this->getConnection()->configName(),
                    $this->getTable(),
                    json_encode($primaryKey)
                );
            }
            $query->cache($cacheKey, $cacheConfig);
        }

        return $query->firstOrFail();
    }

    /**
     * @inheritDoc
     */
    public function query() {
        return new Query($this->getConnection(), $this);
    }

    /**
     * @inheritDoc
     */
    public function updateAll($fields, $conditions) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function deleteAll($conditions) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function exists($conditions) {
        // TODO: Implement exists() method.
    }

    /**
     * @inheritDoc
     */
    public function save(EntityInterface $entity, $options = []) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function delete(EntityInterface $entity, $options = []) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function newEntity($data = null, array $options = []) {
        // TODO: Implement newEntity() method.
    }

    /**
     * @inheritDoc
     */
    public function newEntities(array $data, array $options = []) {
        // TODO: Implement newEntities() method.
    }

    /**
     * @inheritDoc
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = []) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function patchEntities($entities, array $data, array $options = []) {
        throw new NotImplementedException();
    }

    /**
     * Sets the index alias.
     *
     * @param string $alias Index alias
     * @return $this
     */
    public function setAlias($alias) {
        return $this->setName($alias);
    }

    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getAlias() {
        return $this->getName();
    }

    /**
     * Sets the index name
     * @param string $name Index name
     * @return $this
     */
    public function setName($name) {
        $this->_name = $name;

        return $this;
    }

    /**
     * Returns the index name
     * If this isn't set the name will be inferred from the class name
     *
     * @return string
     */
    public function getName() {
        if ($this->_name === null) {
            $name = namespaceSplit(get_class($this));
            $name = substr(end($name), 0, -5);
            $this->_name = Inflector::underscore($name);
        }

        return $this->_name;
    }

    /**
     * Sets the index registry key used to create this table instance.
     *
     * @param string $registryAlias The key used to access this object.
     * @return $this
     */
    public function setRegistryAlias($registryAlias) {
        $this->_registryAlias = $registryAlias;

        return $this;
    }

    /**
     * Returns the index registry key used to create this instance.
     *
     * @return string
     */
    public function getRegistryAlias() {
        if ($this->_registryAlias === null) {
            $this->_registryAlias = $this->getAlias();
        }

        return $this->_registryAlias;
    }

    /**
     * Returns the query as passed.
     *
     * By default findAll() applies no conditions, you
     * can override this method in subclasses to modify how `find('all')` works.
     *
     * @param Query $query The query to find with
     * @param array $options The options to use for the find
     * @return Query The query builder
     */
    public function findAll(Query $query, array $options) {
        return $query;
    }

    /**
     * Calls a finder method directly and applies it to the passed query,
     * if no query is passed a new one will be created and returned
     *
     * @param string $type name of the finder to be called
     * @param Query $query The query object to apply the finder options to
     * @param array $options List of options to pass to the finder
     * @return Query
     * @throws BadMethodCallException
     */
    public function callFinder($type, Query $query, array $options = []) {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . $type;

        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        /* if ($this->_behaviors && $this->_behaviors->hasFinder($type)) {
            return $this->_behaviors->callFinder($type, [$query, $options]);
        } */

        throw new BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }
}