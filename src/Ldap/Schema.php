<?php

namespace MikeWeb\CakeSources\Ldap;

use Cake\Database\Type;
use Cake\Datasource\SchemaInterface;


class Schema implements SchemaInterface {

    /**
     * The name of the table
     *
     * @var string
     */
    protected $_table;

    /**
     * Columns in the table.
     *
     * @var array
     */
    protected $_columns = [];

    /**
     * A map with columns to types
     *
     * @var array
     */
    protected $_typeMap = [];

    /**
     * Indexes in the table.
     *
     * @var array
     */
    protected $_indexes = [];

    /**
     * Options for the table.
     *
     * @var array
     */
    protected $_options = [];

    /**
     * The valid keys that can be used in a column
     * definition.
     *
     * @var array
     */
    protected static $_columnKeys = [
        'type' => null,
        'baseType' => null,
        'length' => null,
        'precision' => null,
        'null' => null,
        'default' => null,
        'comment' => null,
    ];

    /**
     * Additional type specific properties.
     *
     * @var array
     */
    protected static $_columnExtras = [
        'string' => [
            'fixed' => null,
            'collate' => null,
        ],
        'text' => [
            'collate' => null,
        ],
        'tinyinteger' => [
            'unsigned' => null,
        ],
        'smallinteger' => [
            'unsigned' => null,
        ],
        'integer' => [
            'unsigned' => null,
            'autoIncrement' => null,
        ],
        'biginteger' => [
            'unsigned' => null,
            'autoIncrement' => null,
        ],
        'decimal' => [
            'unsigned' => null,
        ],
        'float' => [
            'unsigned' => null,
        ],
    ];

    /**
     * Constructor.
     *
     * @param string $table The table name.
     * @param array $columns The list of columns for the schema.
     */
    public function __construct($table, array $columns=[]) {
        $this->_table = $table;

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }

    /**
     * @inheritDoc
     */
    public function name() {
        return $this->_table;
    }

    /**
     * @inheritDoc
     */
    public function addColumn($name, $attrs) {
        if (is_string($attrs)) {
            $attrs = ['type' => $attrs];
        }

        $valid = static::$_columnKeys;

        if (isset(static::$_columnExtras[$attrs['type']])) {
            $valid += static::$_columnExtras[$attrs['type']];
        }

        $attrs = array_intersect_key($attrs, $valid);
        $this->_columns[$name] = $attrs + $valid;
        $this->_typeMap[$name] = $this->_columns[$name]['type'];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getColumn($name) {
        if (!isset($this->_columns[$name])) {
            return null;
        }

        $column = $this->_columns[$name];
        unset($column['baseType']);

        return $column;
    }

    /**
     * @inheritDoc
     */
    public function hasColumn($name) {
        return isset($this->_columns[$name]);
    }

    /**
     * @inheritDoc
     */
    public function removeColumn($name) {
        unset($this->_columns[$name], $this->_typeMap[$name]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function columns() {
        return array_keys($this->_columns);
    }

    /**
     * @inheritDoc
     */
    public function getColumnType($name) {
        if (!isset($this->_columns[$name])) {
            return null;
        }

        return $this->_columns[$name]['type'];
    }

    /**
     * @inheritDoc
     */
    public function setColumnType($name, $type) {
        if (!isset($this->_columns[$name])) {
            return $this;
        }

        $this->_columns[$name]['type'] = $type;
        $this->_typeMap[$name] = $type;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function baseColumnType($column) {
        if (isset($this->_columns[$column]['baseType'])) {
            return $this->_columns[$column]['baseType'];
        }

        $type = $this->getColumnType($column);

        if ($type === null) {
            return null;
        }

        if (Type::getMap($type)) {
            $type = Type::build($type)->getBaseType();
        }

        return $this->_columns[$column]['baseType'] = $type;
    }

    /**
     * @inheritDoc
     */
    public function isNullable($name) {
        if (!isset($this->_columns[$name])) {
            return true;
        }

        return ($this->_columns[$name]['null'] === true);
    }

    /**
     * @inheritDoc
     */
    public function typeMap() {
        return $this->_typeMap;
    }

    /**
     * @inheritDoc
     */
    public function defaultValues() {
        $defaults = [];

        foreach ($this->_columns as $name => $data) {
            if (!array_key_exists('default', $data)) {
                continue;
            }

            if ($data['default'] === null && $data['null'] !== true) {
                continue;
            }

            $defaults[$name] = $data['default'];
        }

        return $defaults;
    }

    /**
     * @inheritDoc
     */
    public function setOptions($options) {
        $this->_options = array_merge($this->_options, $options);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getOptions() {
        return $this->_options;
    }
}