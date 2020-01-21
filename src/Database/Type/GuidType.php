<?php

namespace SqlServer\Database\Type;


use Cake\Database\Driver;
use Cake\Database\Type\UuidType;
use Cake\Database\Type\BinaryUuidType;



/**
 * Provides behavior for the GUID type
 */
class GuidType extends UuidType {
    
    /**
     * Convert string values to PHP strings.
     *
     * @param mixed $value The value to convert.
     * @param \Cake\Database\Driver $driver The driver instance to convert with.
     * @return string|null
     */
    public function toPHP($value, Driver $driver) {
        if ($value === null) {
            return null;
        }

        return self::mssqlGuidToString($value);
    }
    
    /**
     * Marshals request data into a PHP string
     *
     * @param mixed $value The value to convert.
     * @return string|null Converted value.
     */
    public function marshal($value) {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        
        return self::mssqlGuidToString($value);
    }
    
    public static function mssqlGuidToString($binary, $short=false) {
        if ( !is_string($binary) ) {
            return null;
        }
        
        if (strlen($binary) == 36 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $binary)) {
            return $binary;
        }
        
        if (strlen($binary) != 16) {
            return null;
        }
        
        $version = ord(substr($binary,7,1))>>4;
        
        if ($version < 1 || $version > 4) {
            return null;
        }
        
        $typefield = ord(substr($binary,8,1))>>4;
        
        /*
         * 0??? Reserved for NCS (Network Computing System) backward compatibility
         * 10?? Standard format
         * 110? Reserved for Microsoft Corporation backward compatibility
         * 111? Reserved for future definition
         */
        if (($typefield & bindec(1100)) != bindec(1000)) {
            return null;
        }
        
        $unpacked = unpack('Va/v2b/n2c/Nd', $binary);
        return sprintf('%08X-%04X-%04X-%04X-%04X%08X', $unpacked['a'], $unpacked['b1'], $unpacked['b2'], $unpacked['c1'], $unpacked['c2'], $unpacked['d']);
    }
}
