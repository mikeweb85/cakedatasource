<?php


namespace App\Http\Session;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Session\DatabaseSession;

class CacheDatabaseSession extends DatabaseSession {
    
    public $cacheKey;

    public function __construct(array $config=[]) {
        $this->cacheKey = Configure::read('Session.handler.cache');
        parent::__construct($config);
    }

    // Read data from the session.
    public function read($id) {
        if (false === ($result = Cache::read($id, $this->cacheKey))) {
            $result = parent::read($id);
            
            if (!empty($result)) {
                Cache::write($id, $result, $this->cacheKey);
            }
        }
        
        return $result;
    }

    // Write data into the session.
    public function write($id, $data) {
        if (false !== ($success = parent::write($id, $data))) {
            Cache::write($id, $data, $this->cacheKey);
        }
        
        return $success;
    }

    // Destroy a session.
    public function destroy($id) {
        Cache::delete($id, $this->cacheKey);
        return parent::destroy($id);
    }

    // Removes expired sessions.
    public function gc($expires=null) {
        return Cache::gc($this->cacheKey) && parent::gc($expires);
    }
}