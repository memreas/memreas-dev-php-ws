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

class EventController extends AbstractActionController {
	protected $url = "http://test";
	protected $user_id;
	public function indexAction() {
		$limit = 20;
		$from = 0;
		$em = $this->serviceLocator->get ( 'doctrine.entitymanager.orm_default' );
		$qb = $em->createQueryBuilder ();
		$qb->select ( 'e.name,e.event_id,e.location,e.date,e.friends_can_post,e.friends_can_share,e.public,e.viewable_from,e.viewable_to,e.self_destruct' );
		$qb->from ( 'Application\Entity\Event', 'e' );
		$qb->setMaxResults ( $limit );
		$qb->setFirstResult ( $from );
		$qb->orderBy ( 'e.event_id', 'DESC' );
		$result = $qb->getQuery ()->getResult ();
		$page = 1;
		$response ['status'] = 'failure';
		$response ['message'] = 'No Record Found';
		$response ['page'] = 1;
		$response ['search'] = '';
		
		if ($result) {
			$response ['status'] = 'success';
			$response ['message'] = 'success';
			$response ['page'] = $page;
		}
		
		$response ['data'] = $result;
		
		echo json_encode ( $response );
		return $this->response;
	}
	public function addAction() {
	}
	public function editAction() {
	}
	public function deleteAction() {
	}
}

// end class IndexController
