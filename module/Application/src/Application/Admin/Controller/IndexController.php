<?php

namespace Application\Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Application\Model;
use Application\Form;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Guzzle\Http\Client;
use Application\Model\MemreasConstants;
use Application\memreas\Login;


class IndexController extends AbstractActionController {

    protected $url = "http://test";
    protected $user_id;
       
    public function indexAction() {
  
    }
	public function addAction() {

  
    }
	public function editAction() {

  
    }
	public function deleteAction() {

  
    }
   

}

// end class IndexController
