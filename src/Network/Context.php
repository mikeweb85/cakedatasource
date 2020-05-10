<?php
declare(strict_types=1);

namespace MikeWeb\CakeSources\Network;

use Cake\Core\Exception\Exception;
use Cake\Core\InstanceConfigTrait;


class Context {

    use InstanceConfigTrait;

    /**
     * Default list of tracked PHP transports
     * @var array
     */
    protected $_transports = ['socket', 'http', 'ssl', 'ftp', 'phar', 'mongodb', 'zip'];

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

        $this->setConfig($config);
    }

    /**
     * @return bool
     */
    protected function initialize(): bool {
        $options = [];

        foreach ($this->_transports as $contextBlock) {
            if ( isset($this->_config[$contextBlock]) && is_array($this->_config[$contextBlock])) {
                $options[$contextBlock] = $this->_context[$contextBlock];
            }
        }

        $this->_context = stream_context_create($options);

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