<?php

namespace App\Utility;



use Cake\Utility\Text as CakeText;
use Cake\Utility\Inflector;
use UnexpectedValueException;


class Text extends CakeText {
    
    protected static $_standardReplacements = [
        'EWH'               => 'Ewh',
        'CAB'               => 'Cab',
        'AWS'	            => 'Aws',
        'DC1'	            => 'Dc1',
        'DC3'	            => 'Dc3',
        'DUO'	            => 'Duo',
        'SALES'	            => 'Sales',
        'SUPPORT'           => 'Support',
        'KBOXUSERS'         => 'KboxUsers',
        'DBA'               => 'Dba',
        'VPN'               => 'Vpn',
        'CVJrAdmins'        => 'CvJrAdmins',
        'CloudBolt'         => 'Cloudbolt',
        'CloudPlus'         => 'Cloudplus',
        'UG'                => 'Ug',
        'IPDB'              => 'Ipdb',
        'OVERNIGHT'         => 'Overnight',
        'TAMupdate'         => 'TamUpdate',
        'SMS'               => 'Sms',
        'VP'                => 'Vp',
        'RDS'               => 'Rds',
        'POC'               => 'Poc',
        'FIRSTSHIFT'        => 'FirstShift',
        'SECONDSHIFT'       => 'SecondShift',
        'LEARNING'          => 'Learning',
        'DG'                => 'Dg',
        'TEAM'              => 'Team',
        'KBOXJRADMINS'      => 'KboxJrAdmins',
        'KBOXADMINS'        => 'KboxAdmins',
        'EDGEDOMAIN'        => 'EdgeDomain',
        'DATABANK'          => 'Databank',
        'IMManagement'      => 'ImManagement',
        'MCI'               => 'Mci',
        'LOE'               => 'Loe',
        'GLEdge'            => 'GlEdge',
        'CRAMDB'            => 'CramDb',
        'CVAdmins'          => 'CvAdmins',
        'DataBank'          => 'Databank',
    ];
    
    
    public static function stardardizedStringReplace($string) {
        if ( !is_string($string) ) {
            throw new UnexpectedValueException();
        }
        
        if ( !empty($string) ) {
            $parts = preg_split('/[^\w]/', Inflector::dasherize(str_replace(array_keys(self::$_standardReplacements), array_values(self::$_standardReplacements), $string)));
            
            foreach ($parts as $i=>$part) {
                if (!trim($part)) {
                    unset($parts[$i]);
                    continue;
                }
                
                $parts[$i] = ucfirst(mb_strtolower(trim($part)));
            }
            
            return join('', $parts);
        }
        
        return $string;
    }
    
    
    public static function mssqlGuidToString($binary, $short=false) {
        if (!is_string($binary)) {
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
        
        if (function_exists('mssql_guid_string')) {
            return call_user_func_array('mssql_guid_string', [$binary, $short]);
        }
        
        $unpacked = unpack('Va/v2b/n2c/Nd', $binary);
        return sprintf('%08X-%04X-%04X-%04X-%04X%08X', $unpacked['a'], $unpacked['b1'], $unpacked['b2'], $unpacked['c1'], $unpacked['c2'], $unpacked['d']);
    }
    
    
    public static function isUuid($uuid) {
        return ( strlen($uuid) == 36 && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1 );
    }
}