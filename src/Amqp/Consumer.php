<?php

namespace App\Amqp;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventDispatcherInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use Cake\Utility\Hash;
use BadMethodCallException;
use Exception as PhpException;

class Consumer implements EventListenerInterface, EventDispatcherInterface {
    
    use EventDispatcherTrait;
    
    /**
     * Connection used for all subsequent communications
     * @var AMQPChannel
     */
    protected $_channel;
    
    /**
     * Queue for consumption
     * @var string
     */
    protected $_queue;
    
    /**
     * EventLoop interface
     * @var LoopInterface
     */
    protected $_loop;
    
    /**
     * Maximum number of messages
     * @var int
     */
    protected $_maxMessages;
    
    /**
     * 
     * @var array|null
     */
    protected $_qos;
    
    /**
     * 
     * @var boolean
     */
    protected $_no_ack;
    
    /**
     * 
     * @var TimerInterface
     */
    protected $_intervalTimer;
    
    /**
     * 
     * @var TimerInterface
     */
    protected $_stopTimer;
    
    /**
     * Data to be appended to events
     * @var array
     */
    protected $_data = [];
    
    /**
     * 
     * @var boolean
     */
    protected $_closed = false;
    
    
    public function implementedEvents() {
        return [];
    }
    
    
    /**
     * 
     * @param LoopInterface $loop
     * @param string $queue
     * @param array $options
     */
    public function __construct(LoopInterface $loop, AMQPChannel $channel, $queue, array $data=[], array $options=[]) {
        $options = Hash::merge([
            'interval'          => 0.5,
            'timeout'           => 600,
            'max'               => null,
            'qos'               => null,
            'no_ack'            => false,
        ], $options);
        
        $this->_loop = $loop;
        $this->_channel = $channel;
        $this->_maxMessages = intval($options['max']);
        $this->_no_ack = boolval($options['no_ack']);
        $this->_queue = $queue;
        $this->_data = $data;
        
        if ( !empty($options['timeout']) && 0 > ($timeout = floatval($options['timeout']))) {
            $this->_stopTimer = $this->_loop->addTimer($timeout, [$this, 'close']);
        }
        
        $this->_loop->addSignal(SIGINT, [$this, 'close']);
        $this->_intervalTimer = $this->_loop->addPeriodicTimer(floatval($options['interval']), [$this, 'consume']);
    }
    
    
    public function close() {
        if ( $this->_closed ) {
            return;
        }
        
        $this->dispatchEvent('AmqpConsumer.stop', array_merge(['queue'=>$this->_queue], $this->_data), $this);
        
        if ( !empty($this->_stopTimer) ) {
            $this->_loop->cancelTimer($this->_stopTimer);
        }
        
        $this->_loop->cancelTimer($this->_intervalTimer);
        $this->_closed = true;
    }
    
    
    public function isClosed() {
        return $this->_closed;
    }
    
    
    public function consume() {
        if ( $this->_closed ) {
            throw new BadMethodCallException('This consumer object is closed and cannot receive any more messages.');
        }
        
        $this->dispatchEvent('AmqpConsumer.start', array_merge(['queue'=>$this->_queue], $this->_data), $this);
        
        ## TODO: QOS
        
        $totalMesages = 0;
        
        while ( null !== ($message = $this->_channel->basic_get($this->_queue, $this->_no_ack)) ) {
            
            ## TODO: Mutex locking
            
            foreach ($this->_data as $k=>$v) {
                if ( empty($message->delivery_info[$k]) ) {
                    $message->delivery_info[$k] = $v;
                }
            }
            
            $message->delivery_info['queue'] = $this->_queue;
            
            try {
                $consumeEvent = $this->dispatchEvent('AmqpConsumer.consume', ['message'=>$message, 'queue'=>$this->_queue], $this);
                
            } catch(PhpException $e) {
                ## TODO: Do something?
            }
            
            if ( !empty($consumeEvent) && !$consumeEvent->isStopped() ) {
                $this->_channel->basic_ack($message->delivery_info['delivery_tag']);
            }
            
            if ( !empty($this->_maxMessages) && ++$totalMesages >= $this->_maxMessages ) {
                return;
            }
        }
    }
}