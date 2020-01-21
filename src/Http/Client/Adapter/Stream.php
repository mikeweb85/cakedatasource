<?php

namespace App\Http\Client\Adapter;


use Cake\Utility\Hash;
use Cake\Core\Configure;
use Cake\Http\Client\Request;
use Cake\Http\Client\Adapter\Stream as CakeStream;



class Stream extends CakeStream {
    
    /**
     * Build miscellaneous options for the request.
     *
     * @param \Cake\Http\Client\Request $request The request being sent.
     * @param array $options Array of options to use.
     * @return void
     */
    protected function _buildOptions(Request $request, $options) {
        $this->_contextOptions = Hash::merge(Configure::read('Stream.Context.default.http') ?: [], $this->_contextOptions);
        parent::_buildOptions($request, $options);
    }
    
    
    /**
     * Build SSL options for the request.
     *
     * @param \Cake\Http\Client\Request $request The request being sent.
     * @param array $options Array of options to use.
     * @return void
     */
    protected function _buildSslContext(Request $request, $options) {
        $this->_sslContextOptions = Hash::merge(Configure::read('Stream.Context.default.ssl') ?: [], isset($options['ssl']) ? $options['ssl'] : [], $this->_sslContextOptions);
        parent::_buildSslContext($request, $options);
    }
}