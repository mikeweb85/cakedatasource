<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\I18n;

use App\Model\Table\CurrenciesTable;
use Cake\Core\App;
use Cake\Core\StaticConfigTrait;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\I18n\Number;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use BadMethodCallException;
use Cake\Utility\Hash;
use Cake\Core\Exception\Exception;
use LogicException;


class Currency {

    use StaticConfigTrait {
        setConfig as _setConfig;
    }

    const CACHE_KEY_FORMAT_RATES = 'currencies:rates';

    const CACHE_KEY_FORMAT_LIST = 'currencies:list';

    const CACHE_KEY_FORMAT_ENTITY_ID = 'currencies:entity:%d';

    const CACHE_KEY_FORMAT_ENTITY_SYM = 'currencies:entity:%s';

    /**
     * @var array
     */
    protected static $_providers = [];

    public static function setConfig($key, array $config=null): void {
        if ($config === null) {
            if (!is_array($key)) {
                throw new LogicException('If config is null, key must be an array.');
            }

            foreach ($key as $name=>$settings) {
                static::setConfig($name, $settings);
            }

            return;
        }

        if (isset(static::$_config[$key])) {
            throw new BadMethodCallException(sprintf('Cannot reconfigure existing key "%s"', $key));
        }

        if (is_object($config)) {
            $config = ['className' => $config];
        }

        static::$_config[$key] = $config;
    }

    /**
     * @param string $config
     * @return bool
     */
    public static function hasConfig(string $config) {
        return isset(static::$_config[$config]);
    }

    /**
     * @return array
     */
    public static function getProviders() {
        return array_keys(static::$_config);
    }

    /**
     * @param string $config
     * @return ExchangeRateProvider
     */
    public static function getProvider(string $config='default'): ExchangeRateProvider {
        if ( !isset(static::$_providers[$config]) ) {
            if ( !isset(static::$_config[$config]) ) {
                throw new BadMethodCallException(sprintf('Provider configuration [%s] could not be found.', $config));
            }

            $providerOptions = static::$_config[$config];

            if ( !isset($providerOptions['className']) ) {
                throw new BadMethodCallException('Exchange provider option [className] not found.');
            }

            $className = App::className($providerOptions['className'], 'I18n/ExchangeRateProvider');

            if ( !class_exists($className) ) {
                throw new BadMethodCallException( sprintf('Exchange rate provider class [%s] not found.', $className) );
            }

            static::$_providers[$config] = new $className($providerOptions);
        }

        return static::$_providers[$config];
    }

    /**
     * @param bool $detailed
     * @param bool $reset
     * @return array
     */
    public static function getCurrencies(bool $detailed=false, bool $reset=false): array {
        $cacheConfig = static::getProvider()->getConfig('cacheConfig', 'default');
        $cacheKey = $detailed ? self::CACHE_KEY_FORMAT_LIST : self::CACHE_KEY_FORMAT_RATES;

        if ( $reset === true || false === ($currencies = Cache::read($cacheKey, $cacheConfig)) ) {
            $currencies = TableRegistry::getTableLocator()->get('Currencies')->find('enabled')->orderAsc('symbol')->all();

            if ( $currencies->isEmpty() ) {
                return [];
            }

            $currencies = $currencies->toArray();
            $rates = Hash::combine($currencies, '{n}.symbol', '{n}.rate');

            Cache::writeMany([
                self::CACHE_KEY_FORMAT_RATES        => $rates,
                self::CACHE_KEY_FORMAT_LIST         => $currencies,
            ], $cacheConfig);

            return $detailed ? $currencies : $rates;
        }

        return $currencies;
    }

    /**
     * @param string $symbol
     * @return EntityInterface
     */
    public static function getCurrency(string $symbol): EntityInterface {
        $cacheConfig = static::getProvider()->getConfig('cacheConfig', 'default');
        $idCacheKey = sprintf(Currency::CACHE_KEY_FORMAT_ENTITY_SYM, $symbol);

        if ( false === ($id = Cache::read($idCacheKey, $cacheConfig)) ) {
            $currency = TableRegistry::getTableLocator()->get('Currencies')->find()->where(['symbol'=>$symbol])->firstOrFail();
            $entityCacheKey = sprintf(Currency::CACHE_KEY_FORMAT_ENTITY_ID, $currency->id);

            Cache::writeMany([
                $idCacheKey         => $currency->id,
                $entityCacheKey     => $currency,
            ], $cacheConfig);

            return $currency;
        }

        $entityCacheKey = sprintf(Currency::CACHE_KEY_FORMAT_ENTITY_ID, $id);

        if ( false === ($currency = Cache::read($entityCacheKey, $cacheConfig)) ) {
            $currency = TableRegistry::getTableLocator()->get('Currencies')->get($id);
            Cache::write($entityCacheKey, $currency, $cacheConfig);
        }

        return $currency;
    }

    /**
     * @param string $symbol
     * @return float
     */
    public static function getCurrencyExchangeRate(string $symbol): float {
        $currency = self::getCurrency($symbol);
        return $currency->get('rate');
    }

    /**
     * @param float|int $value
     * @param string $target
     * @param string|null $source
     * @return float
     */
    public static function convert($value, string $target, string $source=null): float {
        if ( $source === null ) {
            $source = Number::defaultCurrency();
        }

        $currencies = (array)self::getCurrencies(false);

        if ( !(array_key_exists($source, $currencies) && array_key_exists($target, $currencies)) ) {
            throw new Exception('Error finding source or target currence exchange rate.');
        }

        if ( $source != Number::defaultCurrency() && $target != Number::defaultCurrency() ) {
            $value = self::convert($value, Number::defaultCurrency(), $source);
        }

        if ( $target == Number::defaultCurrency() ) {
            return floatval($value / $currencies[$source]);
        }

        return floatval($value * $currencies[$target]);
    }

    /**
     * @param Event|null $event
     * @param EntityInterface|null $entity
     */
    public static function pruneCurrencyCache(Event $event=null, EntityInterface $entity=null): void {
        if ( $event !== null && !($event->getSubject() instanceof CurrenciesTable) ) {
            return;
        }

        $cacheKeys = [
            self::CACHE_KEY_FORMAT_RATES,
            self::CACHE_KEY_FORMAT_LIST,
        ];

        if ( $entity instanceof EntityInterface ) {
            $cacheKeys[] = sprintf(self::CACHE_KEY_FORMAT_ENTITY_ID, $entity->id);
            $cacheKeys[] = sprintf(self::CACHE_KEY_FORMAT_ENTITY_SYM, $entity->get('symbol'));
        }

        $caches = [];

        foreach (static::getProviders() as $key) {
            $caches[] = static::getProvider($key)->getConfig('cacheConfig', 'default');
        }

        foreach (array_unique($caches) as $cache) {
            Cache::deleteMany($cacheKeys, $cache);
        }
    }
}
