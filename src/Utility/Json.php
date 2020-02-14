<?php

declare(strict_types=1);

namespace MikeWeb\CakeSources\Utility;

use Cake\Utility\Text;
use Cake\Utility\Hash;

class Json {
    
    const JSON_COMPLEX = 'complex';
    
    const JSON_SIMPLE = 'simple';
    
    protected static $_jsonComplexRegex = '/
          (?(DEFINE)
             (?<number>   -? (?= [1-9]|0(?!\d) ) \d+ (\.\d+)? ([eE] [+-]? \d+)? )    
             (?<boolean>   true | false | null )
             (?<string>    " ([^"\\\\]* | \\\\ ["\\\\bfnrt\/] | \\\\ u [0-9a-f]{4} )* " )
             (?<array>     \[  (?:  (?&json)  (?: , (?&json)  )*  )?  \s* \] )
             (?<pair>      \s* (?&string) \s* : (?&json)  )
             (?<object>    \{  (?:  (?&pair)  (?: , (?&pair)  )*  )?  \s* \} )
             (?<json>   \s* (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) ) \s* )
          )
          \A (?&json) \Z
          /six';
    
    protected static $_jsonRfc4627Regex = '/[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/i';
    
    public static function isValid(string $json, string $method=self::JSON_COMPLEX): bool {
        
    }
}