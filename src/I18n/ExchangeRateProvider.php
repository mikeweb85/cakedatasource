<?php

namespace MikeWeb\CakeSources\I18n;

use Cake\Cache\Cache;
use Cake\Http\Client\Response;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\HttpException;
use Cake\Log\Log;
use DateTime;
use Exception;
use Cake\Http\Client;
use Cake\Core\Configure;
use InvalidArgumentException;
use Cake\Core\InstanceConfigTrait;


abstract class ExchangeRateProvider {

    use InstanceConfigTrait;

    /**
     * @var string Base URL for scoped HTTP client
     */
    protected $baseUrl;

    /**
     * @var boolean
     */
    protected $debug;

    /**
     * @var Client
     */
    protected $http;

    /**
     * ExchangeRateProvider constructor.
     * @param array $options
     */
    public function __construct(array $options=[]) {
        $this->debug = Configure::read('debug', false);

        $options += [
            'cacheConfig'           => 'default',
            'cacheDuration'         => 3600,
            'protocol'              => null,
        ];

        if ( $this->debug ) {
            $options['cacheDuration'] = 120;
        }

        $this->setConfig($options);

        if ( !property_exists($this, 'baseUrl') || $this->baseUrl === null ) {
            throw new InvalidArgumentException(sprintf('Requred property [baseUrl] not found in %s', self::class));
        }

        if ( !isset($this->_config['timeout']) ) {
            $this->setConfig('timeout', 30);
        }

        $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME);

        if ( $options['protocol'] !== null && in_array($options['protocol'], ['http', 'https']) ) {
            $scheme = $options['protocol'];
        }

        $this->http = new Client([
            'host'          => parse_url($this->baseUrl, PHP_URL_HOST),
            'scheme'        => $scheme,
            'timeout'       => $this->_config['timeout'],
        ]);
    }

    /**
     * Return a list of supported currencies
     * @param array $options
     * @return iterable
     * @throws Exception
     */
    abstract public function getCurrencies(array $options=[]): iterable;

    /**
     * Returns current (latest) exchange rates based on base currency
     * @param array $options
     * @return iterable
     * @throws Exception
     */
    abstract public function getLatest(array $options=[]): iterable;

    /**
     * Return a set of exchange rates based on a date range
     * @param DateTime|string $start (format: yyyy-mm-dd)
     * @param DateTime|string $stop (format: yyyy-mm-dd)
     * @param array $options
     * @return iterable
     * @throws Exception
     */
    abstract public function getHistory($start, $stop, array $options=[]): iterable;

    /**
     * Return exchange rates based on a date
     * @param DateTime|string $date (format: yyyy-mm-dd)
     * @param array $options
     * @return iterable
     * @throws Exception
     */
    abstract public function getDate($date, array $options=[]): iterable;

    /**
     * @param array $options
     * @return iterable
     */
    abstract public function getUsage(array $options=[]): iterable;

    /**
     * @param float $value
     * @param string $to
     * @param string|null $from
     * @param array $options
     * @return float
     */
    abstract public function convert(float $value, string $to, string $from=null, array $options=[]): float;

    /**
     * @param string $uri
     * @param string $cacheKey
     * @param array $parameters
     * @param array $options
     * @return array
     */
    protected function _makeApiRequest(string $uri, string $cacheKey, array $parameters=[], array $options=[]): array {
        $options += [
            'reset'             => false,
            'headers'           => [],
            'enable304'         => false,
        ];

        $cacheData = ( $options['reset'] !== true ) ? Cache::read($cacheKey, $this->_config['cacheConfig']) : false;

        if ( $cacheData !== false && $options['enable304'] !== true ) {
            return $cacheData['json'];
        }

        if ( $cacheData !== false && (isset($cacheData['etag']) && isset($cacheData['modified'])) ) {
            $options['headers'] = [
                'If-None-Match'         => $cacheData['etag'],
                'If-Modified-Since'     => $cacheData['modified'],
            ];
        }

        $response = $this->http->get($uri, $parameters, ['headers'=>$options['headers']]);

        if ( $response->getStatusCode() === 304 ) {
            return $cacheData['json'];

        } elseif ( $response->getStatusCode() !== 200 ) {
            $this->_handleApiError($response);
        }

        $json = $response->getJson();

        if ( $json === false || empty($json) ) {
            $this->_handleApiError($response);
        }

        $data = [
            'json'          => $json,
        ];

        if ( $response->hasHeader('Etag') && $response->hasHeader('Date') ) {
            $data += [
                'etag'      => $response->getHeader('Etag')[0],
                'modified'  => $response->getHeader('Date')[0],
            ];
        }

        Cache::write($cacheKey, $data, $this->_config['cacheConfig']);

        return $data['json'];
    }

    /**
     * @param Response $response
     * @throws HttpException;
     */
    protected function _handleApiError(Response $response) {
        $body = $response->getStringBody();
        $json = $response->getJson();

        switch (true) {
            case ( $json === false ):
                $error = 'Invalid JSON response';
                break;

            case ( isset($json['description']) ):
                $error = $json['description'];
                break;

            case ( isset($json['message']) ):
                $error = $json['message'];
                break;

            case ( isset($json['error']) ):
                if ( is_string($json['error']) ) {
                    $error = $json['error'];
                } else {
                    foreach(['message', 'info', 'description', 'summary'] as $key) {
                        if ( isset($json['error'][$key]) ) {
                            $error = $json['error'][$key];
                            break;
                        }
                    }
                }
                break;

            default:
                $error = 'An unspecified error occurred';
        }

        if ( $this->debug ) {
            Log::debug('Error recieved from API.', ['statusCode'=>$response->getStatusCode(), 'error'=>$error, 'body'=>$body]);
        }

        switch ( $response->getStatusCode() ) {
            case 403:
                throw new ForbiddenException($error);
                break;

            case 400:
                throw new BadRequestException($error);
                break;

            default:
                throw new HttpException($error, $response->getStatusCode());
        }
    }

    /**
     * @param $symbols
     * @return string
     */
    protected function _processSymbols($symbols): string {
        switch (true) {
            case is_array($symbols):
                return join(',', array_values($symbols));
                break;

            case is_string($symbols):
                return $symbols;
                break;

            default:
                throw new InvalidArgumentException('Symbols definition is invalid.');
        }
    }
}
