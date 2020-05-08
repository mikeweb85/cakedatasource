<?php

namespace MikeWeb\CakeSources\I18n;

use Cake\Core\Configure;
use Cake\Log\Log;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as PhoneNumberObject;


class PhoneNumber {

    /**
     * @param string $phone
     * @param string $ext
     * @return PhoneNumberObject
     * @throws NumberParseException
     */
    public static function parse($phone, $ext=null) {
        $matches = [];
        
        if ( preg_match('/^\+?(9?011)?(?P<phone>\d+)(x(?P<ext>\d+))?$/i', trim(preg_replace('/[^\dx]/', '', $phone)), $matches) ) {
            $phoneString = $matches['phone'];
            
            if ( strpos($phoneString, '9') === 0 && strlen($phoneString) !== 10) {
                $phoneString = ltrim($phoneString, '9');
            }
            
            $phoneString = ltrim($phoneString, '1');
            $phoneString = ltrim($phoneString, '0');
            
            if ( strlen($phoneString) === 10 ) {
                $phoneString = "1{$phoneString}";
            }
            
            $phone = "+{$phoneString}";
            
            if ( empty($ext) && !empty($matches['ext']) ) {
                $ext = $matches['ext'];
            }
        }
            
        try {
            $ph = PhoneNumberUtil::getInstance()->parse($phone);
            
            if ( !empty($ext) && trim($ext) ) {
                $ph->setExtension(trim($ext));
            }
            
            return $ph;
            
        } catch (NumberParseException $e) {
            if ( Configure::read('debug') === true ) {
                Log::debug("Unable to parse phone number [{$phone}].");
            }
            
            throw $e;
        }
    }

    /**
     *
     * @param PhoneNumber|string $number
     * @param int $format
     * @return string
     * @throws NumberParseException
     */
    public static function format($number, $format=null) {
        if ( !($number instanceof PhoneNumberObject) ) {
            $number = self::parse($number);
        }
        
        if ( empty($format) ) {
            $format = PhoneNumberFormat::RFC3966;
        }
        
        return PhoneNumberUtil::getInstance()->format($number, $format);
    }


    /**
     *
     * @param PhoneNumber|string $number
     * @param string $lang
     * @return string
     * @throws NumberParseException
     */
    public static function describe($number, $lang='en') {
        if ( !($number instanceof PhoneNumberObject) ) {
            $number = self::parse($number);
        }
        
        return PhoneNumberOfflineGeocoder::getInstance()->getDescriptionForNumber($number, $lang, 'US');
    }
}