<?php

namespace MikeWeb\CakeSources\Ldap;

use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;
use Cake\Database\StatementInterface;
use Cake\Datasource\ConnectionInterface;
use MikeWeb\CakeSources\Ldap\Query;
use MikeWeb\CakeSources\Exception\NotImplementedException;


class Connection implements ConnectionInterface {

    /**
     * Contains the configuration params for this connection.
     *
     * @var array
     */
    protected $_config;

    /**
     * Driver object, responsible for creating the real connection
     * and provide specific SQL dialect.
     *
     * @var \Cake\Database\Driver
     */
    protected $_driver;

    /**
     * Whether to log queries generated during this connection.
     *
     * @var bool
     */
    protected $_logQueries = false;

    /**
     * Logger object instance.
     *
     * @var QueryLogger|null
     */
    protected $_logger;

    /**
     * Constructor.
     *
     * @param array $config configuration for connecting to database
     */
    public function __construct($config) {
        $this->_config = $config;

        $driver = '';
        if (!empty($config['driver'])) {
            $driver = $config['driver'];
        }
        $this->setDriver($driver, $config);

        if (!empty($config['log'])) {
            $this->enableQueryLogging($config['log']);
        }
    }

    /**
     * Prepares a SQL statement to be executed.
     *
     * @param string|Query $sql The SQL to convert into a prepared statement.
     * @return StatementInterface
     */
    public function prepare($sql) {
        $statement = $this->_driver->prepare($sql);

        if ($this->_logQueries) {
            ## TODO: Logging
            //$statement = $this->_newLogger($statement);
        }

        return $statement;
    }

    /**
     * @inheritDoc
     */
    public function newQuery() {
        return new Query($this);
    }

    /**
     * Executes a SQL statement and returns the Statement object as result.
     *
     * @param string $sql The SQL query to execute.
     * @return StatementInterface
     */
    public function query($sql) {
        $statement = $this->prepare($sql);
        $statement->execute();

        return $statement;
    }

    /**
     * @inheritDoc
     */
    public function getSchemaCollection() {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function supportsDynamicConstraints() {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function disableSavePoints() {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function configName() {
        if (empty($this->_config['name'])) {
            return '';
        }

        return $this->_config['name'];
    }

    /**
     * @inheritDoc
     */
    public function config() {
        return $this->_config;
    }

    /**
     * @inheritDoc
     */
    public function transactional(callable $transaction) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function disableConstraints(callable $operation) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function logQueries($enable = null) {
        deprecationWarning(
            'Connection::logQueries() is deprecated. ' .
            'Use enableQueryLogging() and isQueryLoggingEnabled() instead.'
        );

        if ($enable === null) {
            return $this->_logQueries;
        }

        $this->_logQueries = $enable;
    }

    /**
     * Enable/disable query logging
     *
     * @param bool $value Enable/disable query logging
     * @return $this
     */
    public function enableQueryLogging($value) {
        $this->_logQueries = (bool)$value;

        return $this;
    }

    /**
     * Disable query logging
     *
     * @return $this
     */
    public function disableQueryLogging() {
        $this->_logQueries = false;

        return $this;
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled() {
        return $this->_logQueries;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated 3.5.0 Use getLogger() and setLogger() instead.
     */
    public function logger($instance = null) {
        deprecationWarning(
            'Connection::logger() is deprecated. ' .
            'Use Connection::setLogger()/getLogger() instead.'
        );

        if ($instance === null) {
            return $this->getLogger();
        }

        $this->setLogger($instance);
    }

    /**
     * Sets a logger
     *
     * @param QueryLogger $logger Logger object
     * @return $this
     */
    public function setLogger($logger) {
        $this->_logger = $logger;

        return $this;
    }

    /**
     * Gets the logger object
     *
     * @return QueryLogger logger instance
     */
    public function getLogger() {
        if ($this->_logger === null) {
            $this->_logger = new QueryLogger();
        }

        return $this->_logger;
    }

    /**
     * Logs a Query string using the configured logger object.
     *
     * @param string $sql string to be logged
     * @return void
     */
    public function log($sql) {
        /* $query = new LoggedQuery();
        $query->query = $sql;
        $this->getLogger()->log($query); */
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = null) {
        return (string)$value;
    }

    /**
     * Executes a query using $params for interpolating values and $types as a hint for each
     * those params.
     *
     * @param string $query SQL to be executed and interpolated with $params
     * @param array $params list or associative array of params to be interpolated in $query as values
     * @param array $types list or associative array of types to be used for casting values in query
     * @return StatementInterface executed statement
     */
    public function execute($query, array $params = [], array $types = []) {
        if (!empty($params)) {
            $statement = $this->prepare($query);
            $statement->bind($params, $types);
            $statement->execute();

        } else {
            $statement = $this->query($query);
        }

        return $statement;
    }
}