<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\I18n\ExchangeRateProvider;

use DateTime;
use Cake\I18n\Number;
use BadMethodCallException;
use InvalidArgumentException;
use MikeWeb\CakeSources\I18n\ExchangeRateProvider;


class OpenExchangeRates extends ExchangeRateProvider {

    const URL_FORMAT_CONVERT = '/api/convert/%f/%s/%s';

    const URL_FORMAT_LATEST = '/api/latest.json';

    const URL_FORMAT_CURRENCIES = '/api/currencies.json';

    const URL_FORMAT_USAGE = '/api/usage.json';

    const URL_FORMAT_TIME_SERIES = '/api/time-series.json';

    const URL_FORMAT_HISTORICAL = '/api/historical/%s.json';

    protected $baseUrl = 'https://openexchangerates.org';

    /**
     * @var array Default config options
     */
    protected $_defaultConfig = [
        'timeout'       => 16,
        'api_key'       => null,
    ];

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getCurrencies()
     */
    public function getCurrencies(array $options=[]): iterable {
        $options += [
            'pretty'            => false,
            'alternatives'      => false,
            'inactive'          => false,
            'details'           => false,
            'reset'             => false,
            'symbols'           => null,
        ];

        $params = [
            'prettyprint'       => $options['pretty'],
            'show_alternative'  => $options['alternatives'],
            'show_inactive'     => $options['inactive'],
        ];

        $uri = self::URL_FORMAT_CURRENCIES;
        $cacheKey = 'OpenExchangeRates:currencies:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        if ( is_array($options['symbols']) && !empty($options['symbols']) ) {
            foreach ($json as $sym=>$desc) {
                if ( !in_array($sym, $options['symbols']) ) {
                    unset($json[$sym]);
                }
            }
        }

        ksort($json, SORT_ASC);

        return ( $options['details'] === false ) ? array_keys($json) : $json;
    }

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getLatest()
     */
    public function getLatest(array $options=[]): iterable {
        $options += [
            'base'              => Number::defaultCurrency(),
            'symbols'           => null,
            'pretty'            => false,
            'alternatives'      => false,
            'reset'             => false,
        ];

        $params = [
            'app_id'            => $this->_config['api_key'],
            'base'              => $options['base'],
            'prettyprint'       => $options['pretty'],
            'show_alternative'  => $options['alternatives'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = self::URL_FORMAT_LATEST;
        $cacheKey = 'OpenExchangeRates:latest:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        ksort($json['rates'], SORT_ASC);

        return $json['rates'];
    }

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getHistory()
     */
    public function getHistory($start, $stop, array $options=[]): iterable {
        $options += [
            'base'              => Number::defaultCurrency(),
            'symbols'           => null,
            'start'             => $start,
            'stop'              => $stop,
            'pretty'            => false,
            'alternatives'      => false,
            'reset'             => false,
        ];

        if ( $options['start'] === null ) {
            throw new InvalidArgumentException('History requires a start period.');
        }

        if ( $options['stop'] === null ) {
            $options['stop'] = $options['start'];
        }

        $start = ( $options['start'] instanceof DateTime ) ? $options['start'] : new DateTime('@'.strtotime($options['start']));
        $stop = ( $options['stop'] instanceof DateTime ) ? $options['stop'] : new DateTime('@'.strtotime($options['stop']));

        if ( $start > $stop ) {
            throw new BadMethodCallException('End date must be equal to or after the start date.');
        }

        $params = [
            'app_id'            => $this->_config['api_key'],
            'base'              => $options['base'],
            'start'             => $start->format('Y-m-d'),
            'end'               => $stop->format('Y-m-d'),
            'prettyprint'       => $options['pretty'],
            'show_alternative'  => $options['alternatives'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = self::URL_FORMAT_TIME_SERIES;
        $cacheKey = 'OpenExchangeRates:history:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        foreach ($json['rates'] as &$rate) {
            ksort($rate, SORT_ASC);
        }

        ksort($json['rates'], SORT_NATURAL);

        return $json['rates'];
    }

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getDate()
     */
    public function getDate($date, array $options=[]): iterable {
        $options += [
            'base'              => Number::defaultCurrency(),
            'symbols'           => null,
            'date'              => $date,
            'pretty'            => false,
            'alternatives'      => false,
            'reset'             => false,
        ];

        if ($options['date'] === null) {
            throw new InvalidArgumentException('getDate() requires a valid date.');
        }

        $date = ($options['date'] instanceof DateTime) ? $options['date'] : new DateTime('@' . strtotime($options['date']));

        $params = [
            'app_id'            => $this->_config['api_key'],
            'base'              => $options['base'],
            'prettyprint'       => $options['pretty'],
            'show_alternative'  => $options['alternatives'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = sprintf(self::URL_FORMAT_HISTORICAL, $date->format('Y-m-d'));
        $cacheKey = 'OpenExchangeRates:date:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        ksort($json['rates'], SORT_ASC);

        return $json['rates'];
    }


    public function getUsage(array $options = []): iterable {
        $options += [
            'pretty'            => false,
            'reset'             => false,
        ];

        $params = [
            'app_id'            => $this->_config['api_key'],
            'prettyprint'       => $options['pretty'],
        ];

        $uri = self::URL_FORMAT_USAGE;
        $cacheKey = 'OpenExchangeRates:usage:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        return $json['data'];
    }

    public function convert(float $value, string $to, string $from=null, array $options=[]): float {
        $options += [
            'pretty'            => false,
            'reset'             => false,
        ];

        $params = [
            'app_id'            => $this->_config['api_key'],
            'prettyprint'       => $options['pretty'],
        ];

        if ( $from === null ) {
            $from = Number::defaultCurrency();
        }

        $uri = sprintf(self::URL_FORMAT_CONVERT, $value, $from, $to);
        $cacheKey = 'OpenExchangeRates:convert:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        return floatval($json['response']);
    }
}
