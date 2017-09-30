<?php 
namespace AppBundle; 

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class Testablesession extends Session {
    
    public function __construct() {
        
        //Detect if phpunit is running
        if (php_sapi_name() == "cli") {
            parent::__construct(new MockFileSessionStorage());
            $this->setId('testsession');
        } else {
            parent::__construct();            
        }
    }
}