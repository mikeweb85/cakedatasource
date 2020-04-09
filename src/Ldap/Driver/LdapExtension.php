<?php

namespace MikeWeb\CakeSources\Ldap\Driver;

use Cake\Database\Driver;
use MikeWeb\CakeSources\Exception\NotImplementedException;


class LdapExtension extends Driver {

    /**
     * LDAP link identifier
     *
     * @var resource|null
     */
    protected $_connection;

    /**
     * @inheritDoc
     */
    public function connect() {
        // TODO: Implement connect() method.
    }

    /**
     * @inheritDoc
     */
    public function enabled() {
        // return in_array('mysql', PDO::getAvailableDrivers(), true);
        return extension_loaded('ldap');
    }

    /**
     * @inheritDoc
     */
    public function releaseSavePointSQL($name) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function savePointSQL($name) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function rollbackSavePointSQL($name) {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function disableForeignKeySQL() {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function enableForeignKeySQL() {
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
    public function queryTranslator($type) {
        // TODO: Implement queryTranslator() method.
    }

    /**
     * @inheritDoc
     */
    public function schemaDialect() {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function quoteIdentifier($identifier) {
        return $identifier;
    }
}