<?php

namespace MikeWeb\CakeSources\Model\Entity;

use Cake\I18n\Number;
use Cake\ORM\Entity;


class Currency extends Entity {

    protected $_virtual = ['default'];

    /**
     * @return bool|null
     */
    public function _getDefault() {
        if ( !isset($this->_properties['symbol']) ) {
            return null;
        }

        return ( $this->_properties['symbol'] == Number::defaultCurrency() );
    }
}
