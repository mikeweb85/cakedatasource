<?php

namespace MikeWeb\CakeSources\Mailer\Transport;

use SendGrid;
use SendGrid\Mail\Mail;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use UnexpectedValueException;
use Exception;
use Cake\Log\Log;


class SendgridTransport extends AbstractTransport {
    
    /**
     * Default config for this class
     * @var array
     */
    protected $_defaultConfig = [
        'apiKey'            => null,
        'reservedKeys'     => ['x-sg-id', 'x-sg-eid', 'received', 'dkim-signature', 'Content-Type', 'Content-Transfer-Encoding', 'To', 'From', 'Subject', 'Reply-To', 'CC', 'BCC'],
    ];

    /**
     * {@inheritDoc}
     * @throws SendGrid\Mail\TypeException
     * @see \Cake\Mailer\AbstractTransport::send()
     */
    public function send(Email $email) {
        if ( empty($this->_config['apiKey'])) {
            throw new UnexpectedValueException('To use SendGrid API you must provide an API key.');
        }
        
        $reservedKeys = isset($this->_config['reservedKeys']) ? $this->_config['reservedKeys'] : [];
        $headers = $email->getHeaders(['sender', 'readReceipt', 'returnPath']);
        
        foreach ($headers as $key=>$header) {
            if ( in_array($key, $reservedKeys) ) {
                unset($headers[$key]);
                
            } else {
                $headers[$key] = str_replace(["\r", "\n"], '', $header);
            }
        }
        
        $subject = str_replace(["\r", "\n"], '', $email->getSubject());
        
        $sendgridEmail = new Mail();
        
        foreach ($email->getFrom() as $fromEmail=>$fromName) {
            $sendgridEmail->setFrom($fromEmail, $fromName);
        }
        
        $sendgridEmail->addHeaders($headers);
        $sendgridEmail->addTos($email->getTo());
        $sendgridEmail->addBccs($email->getBcc());
        $sendgridEmail->addCcs($email->getCc());
        $sendgridEmail->setSubject($subject);
        
        $replyTo = $email->getReplyTo();
        
        if ( !empty($replyTo) ) {
            foreach ($replyTo as $replyToEmail=>$replyToName) {
                $sendgridEmail->setReplyTo($replyToEmail, $replyToName);
            }
        }
        
        $sendgridEmail->addContent('text/plain', $email->message('text'));
        
        $html = in_array($email->getEmailFormat(), ['html', 'both']);
        
        if ( $html ) {
            $sendgridEmail->addContent('text/html', $email->message('html'));
        }
        
        $sendgrid = new SendGrid($this->_config['apiKey']);
        
        try {
            $response = $sendgrid->send($sendgridEmail);
            
            if ( in_array(intval($response->statusCode()), [200, 201, 202, 204]) ) {
                return ['headers'=>$sendgridEmail->getHeaders(), 'message'=>$sendgridEmail->getContents()];
            }
            
        } catch (Exception $e) {
            Log::error( vsprintf('Unable to send mail via SendGrid API. [Status: %d] %s', [$response->statusCode(), $response->body()]));
        }
     
        return false;
    }
}