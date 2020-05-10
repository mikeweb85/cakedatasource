<?php

namespace MikeWeb\CakeSources\Model\Table;

use Cake\Database\Query;
use Cake\ORM\Table;
use Cake\ORM\Rule\ExistsIn;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class ExchangeRatesTable extends Table {

    public function initialize(array $config) {
        $this->setTable('currency_exchange_rates');
        $this->setAlias('ExchangeRates');
        $this->setPrimaryKey('id');
        $this->setDisplayField('symbol');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Currencies')->setForeignKey('currency_id')->setProperty('Currency');

        $this->setSchema([
            'id'                    => ['type'=>'biginteger', 'length'=>20],
            'currency_id'           => ['type'=>'integer', 'length'=>4],
            'value'                 => ['type'=>'decimal'],
            'created'               => ['type'=>'datetime'],
        ]);
    }

    public function validationDefault(Validator $validator) {
        $validator->integer('currency_id');

        return $validator;
    }
}
