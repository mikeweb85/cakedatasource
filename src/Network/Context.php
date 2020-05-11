<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Network;

use Cake\Core\Exception\Exception;
use Cake\Core\InstanceConfigTrait;
use Cake\Utility\Hash;


class Context {

    use InstanceConfigTrait;

    /**
     * @var array
     */
    protected $_contextParams = ['notification'];

    /**
     * Stream context resource
     * @var resource
     */
    protected $_context;

    /**
     * @see https://www.php.net/manual/en/context.php
     * @var array
     */
    protected $_defaultConfig = [
        'base'              => null,
        'default'           => false,
        'notification'      => null,
        'socket'            => [
            'tcp_nodelay'               => true,
        ],
        'http'              => [
            'user_agent'                => 'CakePHP',
        ],
        'ssl'               => [
            'verify_peer'               => true,
            'verify_depth'              => 5,
            'disable_compression'       => false,
            'SNI_enabled'               => true,
            'capture_peer_cert'         => false,
            'capture_peer_cert_chain'   => false,
            'allow_self_signed'         => false,
        ],
    ];

    /**
     * Context constructor.
     * @param array $config
     */
    public function __construct(array $config=[]) {
        if ( isset($config['notification']) && !is_callable($config['notification']) ) {
            unset($config['notification']);
        }

        if ( isset($config['base']) ) {
            $config = Hash::merge(ContextRegistry::getInstance()->get($config['base'])->getConfig(), $config);
        }

        $this->setConfig($config);
    }

    /**
     * @return bool
     */
    protected function initialize(): bool {
        $options = $this->_config;

        unset($options['base'], $options['default']);

        foreach ($options as $wrapper=>$settings) {
            if ( in_array($wrapper, $this->_contextParams) || !is_array($settings) ) {
                unset($options[$wrapper]);
            }
        }

        if ( $this->_config['default'] === true ) {
            $this->_context = stream_context_set_default($options);

        } else {
            $this->_context = stream_context_create($options);
        }

        if ( !is_resource($this->_context) ) {
            throw new Exception('Unrecognized context returned.');
        }

        if ( isset($this->_config['notification']) && is_callable($this->_config['notification']) ) {
            if ( false === stream_context_set_params($this->_context, ['notification'=>$this->_config['notification']]) ) {
                throw new Exception('Notification callback could not be assigned to the context resource.');
            }
        }

        return true;
    }

    /**
     * @return resource
     */
    public function getContext() {
        if ( $this->_context === null ) {
            $this->initialize();
        }

        return $this->_context;
    }
}