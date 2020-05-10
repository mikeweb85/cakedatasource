<?php

namespace MikeWeb\CakeSources\I18n\ExchangeRateProvider;

use MikeWeb\CakeSources\I18n\ExchangeRateProvider;
use BadMethodCallException;
use Cake\Http\Exception\NotImplementedException;
use Cake\I18n\Number;
use DateTime;
use Exception;
use InvalidArgumentException;

class CurrencyLayer extends ExchangeRateProvider {

    const URL_FORMAT_LATEST = '/live';

    const URL_FORMAT_TIME_SERIES = '/timeframe';

    const URL_FORMAT_HISTORICAL = '/historical';

    const URL_FORMAT_CURRENCIES = '/list';

    const URL_FORMAT_CONVERT = '/convert';

    protected $baseUrl = 'https://api.currencylayer.com';

    /**
     * @var array Default config options
     */
    protected $_defaultConfig = [
        'timeout' => 16
    ];

    /**
     * @inheritDoc
     */
    public function getCurrencies(array $options = []): iterable {
        $options += [
            'details'           => false,
            'reset'             => false,
            'symbols'           => null,
        ];

        $params = [
            'access_key'        => $this->_config['api_key'],
        ];

        $uri = self::URL_FORMAT_CURRENCIES;
        $cacheKey = 'CurrencyLayer:currencies:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        if ( is_array($options['symbols']) && !empty($options['symbols']) ) {
            foreach ($json['currencies'] as $sym=>$desc) {
                if ( !in_array($sym, $options['symbols']) ) {
                    unset($json['currencies'][$sym]);
                }
            }
        }

        ksort($json['currencies'], SORT_ASC);

        return ( $options['details'] === false ) ? array_keys($json['currencies']) : $json['currencies'];
    }

    /**
     * @inheritDoc
     */
    public function getLatest(array $options = []): iterable {
        $options += [
            'base'          => Number::defaultCurrency(),
            'symbols'       => null,
            'reset'         => false,
        ];

        $params = [
            'access_key'    => $this->_config['api_key'],
            'source'        => $options['base'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['currencies'] = $this->_processSymbols($options['symbols']);
        }

        $rates = [];
        $uri = self::URL_FORMAT_LATEST;
        $cacheKey = 'CurrencyLayer:latest:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        foreach ($json['quotes'] as $rawSymbol=>$value) {
            $rates[substr($rawSymbol, 3)] = $value;
        }

        ksort($rates, SORT_ASC);

        return $rates;
    }

    /**
     * @inheritDoc
     */
    public function getHistory($start, $stop, array $options = []): iterable {
        $options += [
            'base'          => Number::defaultCurrency(),
            'symbols'       => null,
            'start'         => $start,
            'stop'          => $stop,
            'reset'         => false,
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
            'access_key'    => $this->_config['api_key'],
            'source'        => $options['base'],
            'start_date'    => $start->format('Y-m-d'),
            'end_date'      => $stop->format('Y-m-d'),
        ];

        if ( $options['symbols'] !== null ) {
            $params['currencies'] = $this->_processSymbols($options['symbols']);
        }

        $historicalRates = [];
        $uri = self::URL_FORMAT_TIME_SERIES;
        $cacheKey = 'CurrencyLayer:history:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        foreach ($json['rates'] as $date=>$rates) {
            $historicalRates[$date] = [];

            foreach ($rates as $rawSymbol=>$value) {
                $historicalRates[$date][substr($rawSymbol, 3)] = $value;
            }

            ksort($historicalRates[$date], SORT_ASC);
        }

        ksort($historicalRates, SORT_NATURAL);

        return $historicalRates;
    }

    /**
     * @inheritDoc
     */
    public function getDate($date, array $options = []): iterable {
        $options += [
            'base'          => Number::defaultCurrency(),
            'symbols'       => null,
            'date'          => $date,
            'reset'         => false,
        ];

        if ( $options['date'] === null ) {
            throw new InvalidArgumentException('getDate() requires a date to fetch.');
        }

        $date = ( $options['date'] instanceof DateTime ) ? $options['date'] : new DateTime('@'.strtotime($options['date']));

        $params = [
            'access_key'    => $this->_config['api_key'],
            'source'          => $options['base'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['currencies'] = $this->_processSymbols($options['symbols']);
        }

        $rates = [];
        $uri = sprintf(self::URL_FORMAT_HISTORICAL, $date->format('Y-m-d'));
        $cacheKey = 'CurrencyLayer:date:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        foreach ($json['quotes'] as $rawSymbol=>$value) {
            $rates[substr($rawSymbol, 3)] = $value;
        }

        ksort($rates, SORT_ASC);

        return $rates;
    }

    /**
     * @inheritDoc
     */
    public function getUsage(array $options = []): iterable {
        throw new NotImplementedException('API does not implement this functionality');
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function convert(float $value, string $to, string $from = null, array $options = []): float {
        $options += [
            'reset'             => false,
            'date'              => null,
        ];

        if ( $from === null ) {
            $from = Number::defaultCurrency();
        }

        $params = [
            'access_key'        => $this->_config['api_key'],
            'amount'            => $value,
            'to'                => $to,
            'from'              => $from,
        ];

        if ( $options['date'] instanceof DateTime ) {
            $params['date'] = $options['date']->format('Y-m-d');

        } elseif ( is_string($options['date']) ) {
            $params['date'] = ( new DateTime('@'.strtotime($options['date'])) )->format('Y-m-d');
        }

        $uri = self::URL_FORMAT_CONVERT;
        $cacheKey = 'CurrencyLayer:convert:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        return floatval($json['result']);
    }
}
