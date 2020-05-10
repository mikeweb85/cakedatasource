<?php

namespace App\Model\Table;

use MikeWeb\CakeSources\I18n\Currency;
use MikeWeb\CakeSources\I18n\ExchangeRateProvider\OpenExchangeRates;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\I18n\Number;
use Cake\Log\Log;
use Cake\ORM\Query;
use Cake\Utility\Hash;
use Exception;
use Cake\ORM\Table;
use Cake\ORM\RulesChecker;
use Cake\ORM\Rule\IsUnique;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Cake\Datasource\ConnectionInterface;


class CurrenciesTable extends Table {

    public function initialize(array $config) {
        $this->setTable('currencies');
        $this->setAlias('Currencies');
        $this->setPrimaryKey('id');
        $this->setDisplayField('symbol');

        $this->addBehavior('Timestamp');

        $this->hasMany('ExchangeRates', ['className'=>'CurrencyExchangeRates'])->setForeignKey('currency_id')->setJoinType('left')->setDependent(true);

        $this->setSchema([
            'id'                    => ['type'=>'integer', 'length'=>4],
            'symbol'                => ['type'=>'string', 'length'=>3],
            'description'           => ['type'=>'string', 'null'=>true],
            'rate'                  => ['type'=>'decimal', 'null'=>true, 'precision'=>6],
            'enabled'               => ['type'=>'boolean'],
            'created'               => ['type'=>'datetime'],
            'modified'              => ['type'=>'datetime'],
        ]);
    }

    /**
     * @param Validator $validator
     * @return Validator
     */
    public function validationDefault(Validator $validator) {
        $validator->notEmptyString('symbol');
        // $validator->decimal('rate', 9);
        $validator->boolean('enabled');

        return $validator;
    }

    /**
     * @param RulesChecker $rules
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules) {
        $rules->add(new IsUnique(['symbol']), 'unique-symbol');

        return $rules;
    }

    /**
     * @param Query $query
     * @param array $options
     * @return Query
     */
    public function findEnabled(Query $query, array $options) {
        return $query->where(['enabled'=>true]);
    }

    /**
     * @param Event $event
     * @param EntityInterface $entity
     * @return bool|void
     */
    public function afterSave(Event $event, EntityInterface $entity) {
        $ratesAlias = 'ExchangeRates';

        if ( !$entity->isNew() && $entity->isDirty('rate') ) {
            $original = $entity->extractOriginal(['rate']);

            if ( $entity->get('rate') == $original['rate'] ) {
                return;
            }

            $rate = TableRegistry::getTableLocator()->get($ratesAlias)->newEntity([
                'currency_id'       => $entity->id,
                'value'             => floatval($original['rate']),
            ]);

            TableRegistry::getTableLocator()->get($ratesAlias)->save($rate);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function truncate(): void {
        $this->getConnection()->transactional(function (ConnectionInterface $connection) {
            $connection->execute( TableRegistry::getTableLocator()->get('ExchangeRates')->getSchema()->truncateSql($connection)[0] );
            $connection->execute( $this->getSchema()->truncateSql($connection)[0] );
        });
    }

    /**
     * @param string $provider
     * @param bool $allow_alternate_provider
     * @return bool|int
     */
    public function importCurrencies(string $provider='default', bool $allow_alternate_provider=false) {
        $provider = Currency::getProvider($provider);

        try {
            if ( $provider instanceof OpenExchangeRates || !$allow_alternate_provider) {
                $currencyList = $provider->getCurrencies(['details'=>true]);

            } elseif ( $allow_alternate_provider ) {
                $currencyList = Currency::getProvider('OpenExchangeRates')->getCurrencies(['details'=>true]);
            }

            $latestRates = $provider->getLatest(['reset'=>true]);

        } catch (Exception $e) {
            return false;
        }

        $currencies = 0;

        foreach ($currencyList as $symbol=>$description) {
            $filtered = ( isset($latestRates[$symbol]) );

            $data = [
                'symbol'                => $symbol,
                'description'           => $description,
                'enabled'               => $filtered,
            ];

            if ( $filtered ) {
                $data['rate'] = floatval(Number::precision($latestRates[$symbol], 6));
            }

            $currency = $this->newEntity($data);

            if ( false !== $this->save($currency) ) {
                $currencies++;
            }
        }

        return $currencies;
    }

    /**
     * @param string $provider
     * @return bool|int
     */
    public function updateExchangeRates(string $provider='default') {
        $updates = 0;
        $currencies = $this->find('enabled')->orderAsc('symbol')->all();

        if ( !$currencies->isEmpty() ) {
            $symbols = Hash::extract($currencies->toArray(), '{n}.symbol');

            try {
                $latest = Currency::getProvider($provider)->getLatest(['symbols'=>$symbols, 'reset'=>true]);

            } catch (Exception $e) {
                if ( Configure::read('debug', false) ) {
                    Log::debug('Unable to fetch lates rates from API.', ['provider'=>$provider, 'exception'=>$e]);
                }

                return false;
            }

            foreach ($currencies as $currency) {
                if ( isset($latest[$currency->get('symbol')]) ) {
                    $newRate = floatval(Number::precision($latest[$currency->get('symbol')], 6));

                    if ( $currency->get('rate') !== $newRate ) {
                        $currency->set('rate', $newRate);

                        if ( false !== $this->save($currency) ) {
                            $updates++;
                        }
                    }
                }
            }
        }

        return $updates;
    }
}
