<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Network;

use ArrayIterator;
use Cake\Core\ObjectRegistry;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventListenerInterface;
use Countable;
use Exception;
use IteratorAggregate;


class ContextRegistry implements Countable, IteratorAggregate{

    /**
     * @var ContextRegistry
     */
    protected static $_registry;





    /**
     * @var array
     */
    protected $_contexts = [];

    /**
     * Create an instance of a given classname.
     *
     * This method should construct and do any other initialization logic
     * required.
     *
     * @param string $class The class to build.
     * @param string $alias The alias of the object.
     * @param array $config The Configuration settings for construction
     * @return mixed
     */
    protected function _create($class, $alias, $config) {

    }

    /**
     * Get the list of loaded objects.
     *
     * @return string[] List of object names.
     */
    public function loaded() {
        return array_keys($this->_contexts);
    }

    /**
     * Check whether or not a given object is loaded.
     *
     * @param string $name The object name to check for.
     * @return bool True is object is loaded else false.
     */
    public function has($name) {
        return isset($this->_contexts[$name]);
    }

    /**
     * Get loaded object instance.
     *
     * @param string $name Name of object.
     * @return object|null Object instance if loaded else null.
     */
    public function get($name) {
        if (isset($this->_contexts[$name])) {
            return $this->_contexts[$name];
        }

        return null;
    }

    /**
     * Provide public read access to the loaded objects
     *
     * @param string $name Name of property to read
     * @return mixed
     */
    public function __get($name) {
        return $this->get($name);
    }

    /**
     * Provide isset access to _loaded
     *
     * @param string $name Name of object being checked.
     * @return bool
     */
    public function __isset($name) {
        return isset($this->_contexts[$name]);
    }

    /**
     * Sets an object.
     *
     * @param string $name Name of a property to set.
     * @param mixed $object Object to set.
     * @return void
     */
    public function __set($name, $object) {
        $this->set($name, $object);
    }

    /**
     * Unsets an object.
     *
     * @param string $name Name of a property to unset.
     * @return void
     */
    public function __unset($name) {
        $this->unload($name);
    }

    /**
     * Normalizes an object array, creates an array that makes lazy loading
     * easier
     *
     * @param array $objects Array of child objects to normalize.
     * @return array Array of normalized objects.
     */
    public function normalizeArray($objects) {
        $normal = [];

        foreach ($objects as $i => $objectName) {
            $config = [];

            if (!is_int($i)) {
                $config = (array)$objectName;
                $objectName = $i;
            }

            list(, $name) = pluginSplit($objectName);

            if (isset($config['class'])) {
                $normal[$name] = $config;
            } else {
                $normal[$name] = ['class' => $objectName, 'config' => $config];
            }
        }

        return $normal;
    }

    /**
     * Clear loaded instances in the registry.
     *
     * If the registry subclass has an event manager, the objects will be detached from events as well.
     *
     * @return $this
     */
    public function reset() {
        foreach (array_keys($this->_contexts) as $name) {
            $this->unload($name);
        }

        return $this;
    }

    /**
     * Set an object directly into the registry by name.
     *
     * If this collection implements events, the passed object will
     * be attached into the event manager
     *
     * @param string $objectName The name of the object to set in the registry.
     * @param object $object instance to store in the registry
     * @return $this
     */
    public function set($objectName, $object) {
        list(, $name) = pluginSplit($objectName);

        // Just call unload if the object was loaded before
        if (array_key_exists($objectName, $this->_contexts)) {
            $this->unload($objectName);
        }
        if ($this instanceof EventDispatcherInterface && $object instanceof EventListenerInterface) {
            $this->getEventManager()->on($object);
        }
        $this->_contexts[$name] = $object;

        return $this;
    }

    /**
     * Remove an object from the registry.
     *
     * If this registry has an event manager, the object will be detached from any events as well.
     *
     * @param string $objectName The name of the object to remove from the registry.
     * @return $this
     */
    public function unload($objectName) {
        if (empty($this->_contexts[$objectName])) {
            list($plugin, $objectName) = pluginSplit($objectName);
            $this->_throwMissingClassError($objectName, $plugin);
        }

        $object = $this->_contexts[$objectName];
        if ($this instanceof EventDispatcherInterface && $object instanceof EventListenerInterface) {
            $this->getEventManager()->off($object);
        }
        unset($this->_contexts[$objectName]);

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function count() {
        return count($this->_contexts);
    }

    /**
     * @inheritDoc
     */
    public function getIterator() {
        return new ArrayIterator($this->_contexts);
    }

    /**
     * Debug friendly object properties.
     * @return array
     */
    public function __debugInfo() {
        $properties = get_object_vars($this);

        if (isset($properties['_contexts'])) {
            $properties['_contexts'] = array_keys($properties['_contexts']);
        }

        return $properties;
    }
}