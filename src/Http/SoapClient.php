<?php

namespace MikeWeb\CakeSources\Http;

use SoapVar;
use SoapFault;
use DOMDocument;
use SimpleXMLElement;
use Cake\Utility\Hash;
use Cake\Utility\Text;
use Cake\Utility\Xml;
use Cake\Core\Configure;
use Psr\Log\LogLevel;
use Cake\Log\LogTrait;
use SoapClient as Client;
use Cake\Core\Exception\Exception;


class SoapClient extends Client {
    
    const AUTHENTICATION_NTLM = -1;
    const AUTHENTICATION_BASIC = SOAP_AUTHENTICATION_BASIC;
    const AUTHENTICATION_DIGEST = SOAP_AUTHENTICATION_DIGEST;
    
    use LogTrait;
    
    /**
     * Constructor
     *
     * ### Options:
     * - `debug` boolean - Do you want to see debug info? Default is false
     * - `options` array - Context options for `stream_context` SoapClient Option
     *
     * @param string $wsdl path to SOAP WSDL definition
     * @param array $options The options
     */
    public function __construct($wsdl, $options=[]) {
        if (Configure::read('debug') === true) {
            $this->log($wsdl ?: $options['location'], LogLevel::INFO);
        }
        
        if (isset($options['authentication']) && $options['authentication'] === self::AUTHENTICATION_NTLM) {
            throw new SoapFault(500, 'NTLM authentication not supported.');
        }
        
        if (!isset($options['cache_wsdl'])) {
            $options['cache_wsdl'] = WSDL_CACHE_NONE;
        }
        
        if (!isset($options['trace']) && Configure::read('debug') === true) {
            $options['trace'] = true;
        }
        
        if (!isset($options['exceptions'])) {
            $options['exceptions'] = true;
        }
        
        if (!isset($options['stream_context'])) {
            $defaultContextOptions  = Configure::read('Stream.Context.default', []);
            $soapContextOptions     = Configure::read('Stream.Context.soap', []);
            $contextOptions         = isset($options['options']) ? $options['options'] : [];
            
            $options['stream_context'] = stream_context_create(Hash::merge($defaultContextOptions, $soapContextOptions, $contextOptions));
        }
        
        
        parent::SoapClient($wsdl, $options);
    }
    
    /**
     * Performs a SOAP request
     * @param string $request The XML SOAP request.
     * @param string $location The URL to request.
     * @param string $action The SOAP action.
     * @param int  $version The SOAP version.
     * @param int $oneWay If set to 1, this method returns nothing. Use this where a response is not expected.
     * @return string The XML SOAP response.
     */
    public function __doRequest($request, $location, $action, $version, $oneWay = 0) {
        if ( substr_count($request, '<?xml ') > 1 ) {
            $matches = [];
            $xmlStart = strpos($request, '<?xml ', 10);
            
            if ( $xmlStart !== false && preg_match('/[0-9a-z]{3,8}:Envelope/im', substr($request, $xmlStart), $matches)) {
                $tagStart = "<{$matches[0]} ";
                $tagStop = "</{$matches[0]}>";
                
                if (false === ($xmlBodyStart = strpos($request, $tagStart))) {
                    throw new SoapFault(500, 'Invald XML format, envelope open tag not found.');
                }
                
                $newRequest = substr($request, $xmlBodyStart);
                
                if (false === ($xmlBodyStop = strpos($newRequest, $tagStop) + strlen($tagStop))) {
                    throw new SoapFault(500, 'Invald XML format, envelope closing tag not found.');
                }
                
                $newRequest = substr($newRequest, 0, $xmlBodyStop);
                
                try {
                    $xml = Xml::build($newRequest);
                    
                } catch(Exception $e) {
                    throw new SoapFault(500, 'Unable to parse XML request.');
                }
                
                $request = $xml->asXML();
            }
        }
        
        if (Configure::read('debug') === true) {
            $this->log($request, LogLevel::INFO);
            $this->log($location, LogLevel::INFO);
            $this->log($action, LogLevel::INFO);
            $this->log($version, LogLevel::INFO);
        }
        
        return parent::__doRequest($request, $location, $action, $version, $oneWay);
    }
    
    /**
     * Calls a SOAP function
     * @param string $functionName The name of the SOAP function to call.
     * @param array $arguments An array of the arguments to pass to the function.
     * @param array $options An associative array of options to pass to the client.
     * @param array $inputHeaders An array of headers to be sent along with the SOAP request.
     * @param array $outputHeaders If supplied, this array will be filled with the headers from the SOAP response.
     * @return mixed
     */
    public function __soapCall($functionName, $arguments=[], $options=[], $inputHeaders=[], &$outputHeaders=[]) {
        if (Configure::read('debug') === true) {
            $this->log($functionName, LogLevel::INFO);
            $this->log($arguments, LogLevel::INFO);
            $this->log($options, LogLevel::INFO);
            $this->log($inputHeaders, LogLevel::INFO);
            $this->log($outputHeaders, LogLevel::INFO);
        }
        
        return parent::__soapCall($functionName, $arguments, $options, $inputHeaders, $outputHeaders);
    }
    
    /**
     * Calls a SOAP function from a request template
     * @param string $action The name of the SOAP function to call.
     * @param array $data An array of the arguments to pass to the function.
     * @param string $template Template for request body
     * @param array $options An associative array of options to pass to the client.
     * @param array $inputHeaders An array of headers to be sent along with the SOAP request.
     * @param array $outputHeaders If supplied, this array will be filled with the headers from the SOAP response.
     * @return mixed
     */
    public function __soapCallFromTemplate($action, $data=[], $template, $options=[], $inputHeaders=[], &$outputHeaders=[]) {
        $request = Text::insert($template, $data);
        
        if (preg_match('/[0-9a-z]{3,8}:Envelope/im', $request)) {
            try {
                $request = Xml::build($request)->asXML();
                
            } catch (Exception $e) {
                throw new SoapFault(500, 'Unable to parse XML request.');
            }
        }
        
        return parent::__soapCall($action, [new SoapVar($request, XSD_ANYXML)], $options, $inputHeaders, $outputHeaders);
    }
    
    /**
     * Calls a SOAP function from a SimpleXML Element
     * @param string $action The name of the SOAP function to call.
     * @param array $data An array of the arguments to pass to the function.
     * @param string $template Template for request body
     * @param array $options An associative array of options to pass to the client.
     * @param array $inputHeaders An array of headers to be sent along with the SOAP request.
     * @param array $outputHeaders If supplied, this array will be filled with the headers from the SOAP response.
     * @return mixed
     */
    public function __soapCallFromXml($action, $xml, $options=[], $inputHeaders=[], &$outputHeaders=[]) {
        if (is_string($xml)) {
            try {
                $xml = Xml::build($xml);
                
            } catch (Exception $e) {
                throw new SoapFault(500, 'Unable to parse XML request.');
            }
        }
        
        switch (true) {
            case ($xml instanceof SimpleXMLElement):
                $request = $xml->asXML();
                break;
                
            case ($xml instanceof DOMDocument):
                $request = $xml->saveXML();
                break;
                
            default:
                throw new SoapFault(500, 'Invalid XML object provided for request.');
        }
        
        return parent::__soapCall($action, [new SoapVar($request, XSD_ANYXML)], $options, $inputHeaders, $outputHeaders);
    }
}