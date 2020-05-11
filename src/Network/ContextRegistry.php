<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Network;

use ArrayIterator;
use Cake\Core\App;
use Cake\Core\ObjectRegistry;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventListenerInterface;
use Countable;
use Exception;
use IteratorAggregate;
use MikeWeb\CakeSources\Network\Exception\MissingContextException;


class ContextRegistry extends ObjectRegistry {

    /**
     * @var ContextRegistry
     */
    protected static $_registry;

    /**
     * @return ContextRegistry
     */
    public static function getInstance(): ContextRegistry {
        if ( static::$_registry === null || !(static::$_registry instanceof ContextRegistry) ) {
            static::$_registry = new ContextRegistry();
        }

        return static::$_registry;
    }

    /**
     * @inheritDoc
     */
    protected function _resolveClassName($class) {
        if (is_object($class)) {
            return $class;
        }

        if ( $class == Context::class ) {
            return $class;
        }

        return App::className($class, 'Network/Context', 'Context');
    }

    /**
     * @inheritDoc
     * @throws MissingContextException
     */
    protected function _throwMissingClassError($class, $plugin) {
        throw new MissingContextException(['class' => $class, 'plugin' => $plugin]);
    }

    /**
     * @inheritDoc
     */
    protected function _create($class, $alias, $config) {
        if (is_callable($class)) {
            return $class($alias);
        }

        if (is_object($class)) {
            return $class;
        }

        unset($config['className']);

        return new $class($config);
    }
}