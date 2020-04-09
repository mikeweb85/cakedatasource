<?php

namespace MikeWeb\CakeSources\Ldap;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;


class Entity implements EntityInterface {

    use EntityTrait;


    protected $_result;

    /**
     * Takes either an array or a Result object form a search and constructs
     * a document representing an entity in a elastic search type,
     *
     * @param array $data An array or Result object that represents an Elasticsearch document
     * @param array $options An array of options to set the state of the document
     */
    public function __construct($data=[], $options=[]) {
        $options += [
            'useSetters' => true,
            'markClean' => false,
            'markNew' => null,
            'guard' => false,
            'source' => null,
            'result' => null
        ];

        if (!empty($options['source'])) {
            $this->setSource($options['source']);
        }

        if ($options['markNew'] !== null) {
            $this->isNew($options['markNew']);
        }

        if ($options['result'] !== null) {
            $this->_result = $options['result'];
        }

        if (!empty($data) && $options['markClean'] && !$options['useSetters']) {
            $this->_properties = $data;

            return;
        }

        if (!empty($data)) {
            $this->set($data, [
                'setter' => $options['useSetters'],
                'guard' => $options['guard']
            ]);
        }

        if ($options['markClean']) {
            $this->clean();
        }
    }
}