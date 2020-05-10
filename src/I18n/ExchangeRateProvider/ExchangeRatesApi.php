<?php

namespace MikeWeb\CakeSources\I18n\ExchangeRateProvider;

use Cake\Http\Exception\NotImplementedException;
use DateTime;
use Exception;
use Cake\Cache\Cache;
use Cake\I18n\Number;
use BadMethodCallException;
use InvalidArgumentException;
use MikeWeb\CakeSources\I18n\ExchangeRateProvider;
use Cake\Http\Exception\HttpException;


class ExchangeRatesApi extends ExchangeRateProvider {

    const URL_FORMAT_LATEST = '/latest';

    const URL_FORMAT_TIME_SERIES = '/history';

    const URL_FORMAT_HISTORICAL = '/%s';

    protected $baseUrl = 'https://api.exchangeratesapi.io';

    /**
     * @var array Default config options
     */
    protected $_defaultConfig = [
        'timeout'       => 16
    ];

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getCurrencies()
     */
    public function getCurrencies(array $options=[]): iterable {
        $options += [
            'details'       => false,
            'reset'         => false,
        ];

        $rates = self::getLatest($options);
        $currencies = array_keys( (array)$rates );

        return ( $options['details'] === false ) ? $currencies : array_combine($currencies, array_pad([], count($currencies), ''));
    }

    /**
     * {@inheritDoc}
     * @see \MikeWeb\CakeSources\I18n\ExchangeRateProvider::getLatest()
     */
    public function getLatest(array $options=[]): iterable {
        $options += [
            'base'          => Number::defaultCurrency(),
            'symbols'       => null,
            'reset'         => false,
        ];

        $params = [
            'base'      => $options['base'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = self::URL_FORMAT_LATEST;
        $cacheKey = 'ExchangeRatesApi:latest:' . hash('md5', serialize([$uri, $params]));
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
            'base'          => $options['base'],
            'start_at'      => $start->format('Y-m-d'),
            'end_at'        => $stop->format('Y-m-d'),
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = self::URL_FORMAT_TIME_SERIES;
        $cacheKey = 'ExchangeRatesApi:history:' . hash('md5', serialize([$uri, $params]));
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
            'base'          => $options['base'],
        ];

        if ( $options['symbols'] !== null ) {
            $params['symbols'] = $this->_processSymbols($options['symbols']);
        }

        $uri = sprintf(self::URL_FORMAT_HISTORICAL, $date->format('Y-m-d'));
        $cacheKey = 'ExchangeRatesApi:date:' . hash('md5', serialize([$uri, $params]));
        $json = $this->_makeApiRequest($uri, $cacheKey, $params, $options);

        ksort($json['rates'], SORT_ASC);

        return $json['rates'];
    }

    public function getUsage(array $options = []): iterable {
        throw new NotImplementedException('API does not implement this functionality');
    }

    public function convert(float $value, string $to, string $from=null, array $options=[]): float {
        throw new NotImplementedException('API does not implement this functionality');
    }
}
