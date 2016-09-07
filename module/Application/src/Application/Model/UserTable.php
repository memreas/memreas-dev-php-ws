<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

// For join tables
use Zend\Db\Sql\Sql;
// For condtion statment
use Zend\Db\Sql\Where;
use Zend\Db\ResultSet;
use Zend\Db\Sql\Select;
use Application\memreas\MNow;

class UserTable {
	protected $tableGateway;
	public function __construct(TableGateway $tableGateway) {
		$this->tableGateway = $tableGateway;
	}
	public function fetchAll($where = null) {
		$resultSet = $this->tableGateway->select ();
		$resultSet->buffer ();
		$resultSet->next ();
		return $resultSet;
	}
	public function getUser($id) {
		$rowset = $this->tableGateway->select ( array (
				'user_id' => $id 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $id" );
		}
		return $row;
	}
	public function saveUser(User $user) {
		
		$this -> user_id = (isset($data['user_id'])) ? $data['user_id'] : null;
		$this -> username = (isset($data['username'])) ? $data['username'] : null;
		$this -> email_address = (isset($data['email_address'])) ? $data['email_address'] : null;
		$this -> metadata = (isset($data['metadata'])) ? $data['metadata'] : null;
		$this -> disable_account = (isset($data['disable_account'])) ? $data['disable_account'] : null;
		$this -> create_timestamp = (isset($data['create_timestamp'])) ? $data['create_timestamp'] : null;
		$this -> update_timestamp = (isset($data['update_timestamp'])) ? $data['update_timestamp'] : null;
		$this -> create_time = (isset($data['create_time'])) ? $data['create_time'] : null;
		$this -> update_time = (isset($data['update_time'])) ? $data['update_time'] : null;
		
		// (isset($user->user_id)) ? $data['user_id']= $user->user_id : null;
		(isset ( $user->username )) ? $data ['username'] = $user->username : null;
		(isset ( $user->email_address )) ? $data ['email_address'] = $user->email_address : null;
		(isset ( $user->role )) ? $data ['role'] = $user->role : null;
		(isset ( $user->metadata )) ? $data ['metadata'] = $user->metadata : null;
		(isset ( $user->create_timestamp )) ? $data ['create_timestamp'] = $user->create_timestamp : null;
		(isset ( $user->update_timestamp )) ? $data ['update_timestamp'] = MNow::now() : null;
		(isset ( $user->create_time )) ? $data ['create_time'] = $user->create_time : null;
		(isset ( $user->update_time )) ? $data ['update_time'] = time() : null;
		
		$id = $user->user_id;
		if (empty ( $id )) {
			$data ['create_date'] = strtotime ( date ( Y - m - d ) );
			$this->tableGateway->insert ( $data );
			return true;
		} else {
			if ($this->getUser ( $id )) {
				$this->tableGateway->update ( $data, array (
						'user_id' => $id 
				) );
				return true;
			} else {
				throw new \Exception ( 'User does not exist' );
			}
		}
	}
	public function deleteUser($id) {
		$this->tableGateway->delete ( array (
				'user_id' => $id 
		) );
	}
	public function getUserByUsername($username) {
		$rowset = $this->tableGateway->select ( array (
				'username' => $username 
		) );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row $id" );
		}
		return $row;
	}
	public function getUserByRole($role) {
		$rowset = $this->tableGateway->select ( array (
				'role' => $role 
		) );
		return $rowset;
	}
	public function getUserBy($where) {
		$rowset = $this->tableGateway->select ( $where );
		$row = $rowset->current ();
		if (! $row) {
			throw new \Exception ( "Could not find row " );
		}
		return $row;
	}
	public function isExist($where) {
		$select = new Select ();
		$select->from ( $this->tableGateway->getTable () )->where->NEST->like ( 'username', $where ['username'] )->or->like ( 'email_address', $where ['email_address'] )->UNNEST->and->notEqualTo ( 'user_id', $where ['user_id'] );
		
		$statement = $this->tableGateway->getAdapter ()->createStatement ();
		$select->prepareStatement ( $this->tableGateway->getAdapter (), $statement );
		
		$resultSet = new ResultSet\ResultSet ();
		$resultSet->initialize ( $statement->execute () );
		
		// echo "<pre>";echo $select->getSqlString();
		// print_r($resultSet->current());
		// exit(0);
		//
		if ($resultSet->current ())
			return true;
		else
			return false;
	}
}