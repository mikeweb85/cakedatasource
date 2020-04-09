<?php

namespace MikeWeb\CakeSources\ElasticSearch;

use Cake\Utility\Inflector;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\Index as CakeIndex;
use MikeWeb\CakeSources\ElasticSearch\ThreadedTrait;

class Index extends CakeIndex {

    use ThreadedTrait;

    public function initialize(array $config) {
        $this->setType( $this->getName() );
    }
}