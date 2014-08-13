<?php

namespace Application\memreas;

use Application\Model\MemreasConstants AS MC;
use Application\memreas\AWSManagerSender;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Model\ViewModel;
use \Exception;

class Email {
	protected static $service_locator;
	protected static $dbAdapter;
        public static $item;
        protected static $collection;
                        
	public static function collect() {
                 self::$collection [] = self::$item;
                self::$item = array();	
	}
	
        public static function ok(){
            return empty($item->ok)?false:TRUE;
        }
	 
	 
	public static  function sendmail($servicemanager){
			$aws_manager = new AWSManagerSender($servicemanager);
            $viewModel = new ViewModel (array());
            
            
            $viewRender = $servicemanager->get('ViewRenderer');
            foreach (self::$collection as $value) {
                  $email = self::getSubject($value);
                  $viewModel->setTemplate($email['template']);
                  $subject =  $email['subject'];
                  $viewModel->clearVariables();
                  $viewModel->setVariables($value);
                  echo '<pre>';print_r($value);print_r($email);
                  $html = $viewRender->render($viewModel);
            echo $html ;exit;
                try {
                	$aws_manager->sendSeSMail($value->email, $subject, $html);
            	} catch (\Exception $exc) {
                	error_log('exception->sending mail' . $exc->getMessage());
            	}
            } 
	}
        public static  function getSubject($item){
            
          $data= array(
              'user-registration'=>array('subject' =>"Welcome to event app" ,
                                    'message'=>'',
                                    'template' =>'email/register'),
              'event-invite'=>array('subject' =>"Event Invitation" ,
                                    'template' =>'email/event-invite'),
              'friend-request'=>array('subject' =>"Friend Request" ,
                                    'template' =>'email/event-invite'),
              'user-registration'=>array('subject' =>"" ,
                                    'template' =>''),
              'user-changepassword'=>array('subject' =>"" ,
                                    'template' =>''),
              'user-forgetpassword'=>array('subject' =>"" ,
                                    'template' =>'email/forgotpassword'),
              'user-comment'=>array('subject' =>"New Comment" ,
                                    'template' =>'email/user-comment'),
              'registration'=>array('subject' =>"" ,
                                    'template' =>''),
              'registration'=>array('subject' =>"" ,
                                    'template' =>''),
              
               

          );
            
            return $data[$item['type']];
            
        }

}

?>
