<?php

namespace App\Http\Client\Adapter;


use Cake\Http\Client\Request;
use Cake\Http\Client\Response;
use Cake\Core\Exception\Exception;
use Cake\Network\Exception\HttpException;
use App\Http\Client\Adapter\Stream;



class Curl extends Stream {
    
    /**
     * The stream resource.
     *
     * @var resource|null
     */
    protected $_stream;
    
    
    /**
     * Build the stream context out of the request object.
     *
     * @param \Cake\Http\Client\Request $request The request to build context from.
     * @param array $options Additional request options.
     * @return void
     */
    protected function _buildContext(Request $request, $options) {
        $this->_buildContent($request, $options);
        $this->_buildHeaders($request, $options);
        $this->_buildOptions($request, $options);
        
        $url = $request->getUri();
        $scheme = parse_url($url, PHP_URL_SCHEME);
        
        if ($scheme === 'https') {
            $this->_buildSslContext($request, $options);
        }
        
        if (isset($options['auth'])) {
            $this->_contextOptions['auth'] = $options['auth'];
        }
    }
    
    
    /**
     * Open the stream and send the request.
     *
     * @param \Cake\Http\Client\Request $request The request object.
     * @return array Array of populated Response objects
     * @throws \Cake\Network\Exception\HttpException
     */
    protected function _send(Request $request) {
        $url = $request->getUri();
        $this->_open($url);
        $this->_setCurlOptions($url);
        
        $content = '';
        $timedOut = false;
        
        $response = curl_exec($this->_stream);
        $error = curl_errno($this->_stream);
        
        if ($error) {
            throw new Exception(curl_error($this->_stream));
        }
        
        curl_close($this->_stream);
        
        if ($timedOut) {
            throw new HttpException("Connection timed out {$url}", 504);
        }
        
        $headers = mb_substr($response, 0, mb_strpos($response, "\r\n\r\n"));
        $content = trim(mb_substr($response, mb_strlen($headers)));
        
        if (0 === mb_strpos($content, 'HTTP/')) {
            $headers = mb_substr($content, 0, mb_strpos($content, "\r\n\r\n"));
            $content = trim(mb_substr($content, mb_strlen($headers)));
        }
        
        return $this->createResponses(explode("\r\n", $headers), $content);
    }
    
    /**
     * Open the socket and handle any connection errors.
     *
     * @param string $url The url to connect to.
     * @return void
     * @throws \Cake\Core\Exception\Exception
     */
    protected function _open($url) {
        set_error_handler(function ($code, $message) {
            $this->_connectionErrors[] = $message;
        });
        
        $this->_stream = curl_init($url);
        restore_error_handler();
        
        if (!$this->_stream || !empty($this->_connectionErrors)) {
            throw new Exception(implode("\n", $this->_connectionErrors));
        }
    }
    
    
    
    
    
    protected function _setCurlOptions($url) {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        
        curl_setopt($this->_stream, CURLOPT_CRLF, false);
        curl_setopt($this->_stream, CURLOPT_HEADER, true);
        curl_setopt($this->_stream, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($this->_stream, CURLOPT_FORBID_REUSE, false);
        curl_setopt($this->_stream, CURLOPT_RETURNTRANSFER, true);
        
        foreach ($this->_contextOptions as $option=>$value) {
            switch ($option) {
                case 'header':
                    $headers = explode("\r\n", $value);
                    
                    foreach ($headers as $i=>$header) {
                        if (0 === strpos($header, 'Authorization')) {
                            unset($headers[$i]);
                        }
                    }
                    
                    curl_setopt($this->_stream, CURLOPT_HTTPHEADER, $headers);
                    break;
                    
                case 'method':
                    if ( in_array(strtoupper($value), array('POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'))) {
                        curl_setopt($this->_stream, CURLOPT_POST, true);
                        curl_setopt($this->_stream, CURLOPT_CUSTOMREQUEST, strtoupper($value));
                        
                    } else {
                        curl_setopt($this->_stream, CURLOPT_HTTPGET, true);
                    }
                    break;
                    
                case 'content':
                    if (trim($value)) {
                        curl_setopt($this->_stream, CURLOPT_POSTFIELDS, $value);
                    }
                    break;
                    
                case 'protocol_version':
                    switch ($value) {
                        case '1.0':
                            $version = CURL_HTTP_VERSION_1_0;
                            break;
                            
                        case '1.1':
                            $version = CURL_HTTP_VERSION_1_1;
                            break;
                            
                        default:
                            $version = CURL_HTTP_VERSION_NONE;
                    }
                    
                    curl_setopt($this->_stream, CURLOPT_HTTP_VERSION, $version);
                    unset($version);
                    break;
                    
                case 'timeout':
                    curl_setopt($this->_stream, CURLOPT_TIMEOUT, $value);
                    break;
                    
                case 'max_redirects':
                    curl_setopt($this->_stream, CURLOPT_FOLLOWLOCATION, ($value > 0));
                    
                    if ($value > 0) {
                        curl_setopt($this->_stream, CURLOPT_MAXREDIRS, $value);
                    }
                    break;
                    
                case 'auth':
                    if (!is_array($value) || empty($value)) {
                        continue;
                    }
                    
                    switch ($value['type']) {
                        case 'ntlm':
                            $mode = CURLAUTH_NTLM;
                            break;
                            
                        case 'digest':
                            $mode = CURLAUTH_DIGEST;
                            break;
                            
                        case 'basic':
                            $mode = CURLAUTH_BASIC;
                            break;
                            
                        case 'any':
                            $mode = CURLAUTH_ANY;
                            break;
                            
                        default:
                            $mode = CURLAUTH_ANYSAFE;
                    }
                    
                    curl_setopt($this->_stream, CURLOPT_HTTPAUTH, $mode);
                    curl_setopt($this->_stream, CURLOPT_USERPWD, "{$value['username']}:{$value['password']}");
                    unset($this->_contextOptions['auth'], $mode);
                    break;
            }
        }
        
        unset($option, $value);
        
        if ($scheme == 'https') {
            foreach ($this->_sslContextOptions as $option=>$value) {
                switch ($option) {
                    case 'verify_peer':
                        curl_setopt($this->_stream, CURLOPT_SSL_VERIFYPEER, $value);
                        break;
                        
                    case 'verify_peer_name':
                        curl_setopt($this->_stream, CURLOPT_SSL_VERIFYHOST, $value ? 2 : 0);
                        break;
                        
                    case 'tls_version':
                        switch ($value) {
                            case '1':
                            case '1.0':
                                $version = defined('CURL_SSLVERSION_TLSv1_0') ? CURL_SSLVERSION_TLSv1_0 : CURL_SSLVERSION_TLSv1_0;
                                break;
                                
                            case '1.1':
                                $version = defined('CURL_SSLVERSION_TLSv1_1') ? CURL_SSLVERSION_TLSv1_1 : CURL_SSLVERSION_DEFAULT;
                                break;
                                
                            case '1.2':
                                $version = defined('CURL_SSLVERSION_TLSv1_2') ? CURL_SSLVERSION_TLSv1_2 : CURL_SSLVERSION_DEFAULT;
                                break;
                                
                            default:
                                $version = CURL_SSLVERSION_DEFAULT;
                        }
                        
                        curl_setopt($this->_stream, CURLOPT_SSLVERSION, $version);
                        unset($version);
                        break;
                        
                    case 'cafile':
                        curl_setopt($this->_stream, CURLOPT_CAINFO, $value);
                        break;
                        
                    case 'capath':
                        curl_setopt($this->_stream, CURLOPT_CAPATH, $value);
                        break;
                        
                    case 'local_cert':
                        curl_setopt($this->_stream, CURLOPT_SSLCERT, $value);
                        break;
                        
                    case 'local_pk':
                        curl_setopt($this->_stream, CURLOPT_SSLKEY, $value);
                        break;
                        
                    case 'passphrase':
                        curl_setopt($this->_stream, CURLOPT_SSLCERTPASSWD, $value);
                        break;
                        
                    case 'ciphers':
                        ## TODO: Fix ciphers list output to match expected format
                        // curl_setopt($this->_stream, CURLOPT_SSL_CIPHER_LIST, join(',', explode(':', $value)));
                        break;
                }
            }
        }
    }
}