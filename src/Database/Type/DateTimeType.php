<?php

namespace MikeWeb\CakeSources\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type\DateTimeType as CakeDateTimeType;


class DateTimeType extends CakeDateTimeType {
    
    /**
     * String format to use for DateTime parsing
     * @var string|array
     */
    protected $_format = [
        'Y-m-d H:i:s.u',
        'Y-m-d\TH:i:s.uP',
    ];
    
    /**
     * Convert strings into DateTime instances.
     * @param string $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     * @return \Cake\I18n\Time|\DateTime|null
     */
    public function toPHP($value, Driver $driver) {
        if ($value === null || strpos($value, '0000-00-00') === 0) {
            return null;
        }
        
        $instance = clone $this->_datetimeInstance;
        
        return $instance->modify($value);
    }
}


