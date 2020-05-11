<?php

namespace MikeWeb\CakeSources\Network\Exception;

use Cake\Core\Exception\Exception;

/**
 * Used when a context cannot be found.
 */
class MissingContextException extends Exception
{
    protected $_messageTemplate = 'Context class %s could not be found. %s';
}
