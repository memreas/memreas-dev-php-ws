<?php

namespace Application\Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
 


class UserController extends AbstractActionController {


    protected $url = "http://test";
    protected $user_id;

	
       
    public function indexAction() {
		$em = $this->serviceLocator->get('doctrine.entitymanager.orm_default');
		$qb = $em->createQueryBuilder();
        $qb->select('u.disable_account,u.facebook_username,u.twitter_username,u.user_id','u.username','u.role','u.profile_photo','u.email_address');
        $qb->from('Application\Entity\User','u');

        $qb->orderBy('u.user_id' ,'DESC');
        $result = $qb->getQuery()->getResult();
$page = 1;
$response['status'] = 'failure';
$response['message'] = 'No Record Found';
$response['page'] = 1;
$response['search'] = '';

if($result){
    $response['status'] = 'success';
	$response['message'] = 'success';
	$response['page'] = $page;

    
    
    
    
}

$response['data'] = $result;

  echo json_encode($response);
  return $this->response;

    }
	public function addAction() {

  
    }
	public function editAction() {

  
    }
	public function deleteAction() {

  
    }
	
   public function init()
    {
        
    }
	
}

// end class IndexController
