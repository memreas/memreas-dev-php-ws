<?php
namespace Application\memreas;

class MemreasTables {

	protected $service_locator = NULL;
	protected $accountTable = NULL;
	protected $accountBalancesTable = NULL;
	protected $accountDetailTable = NULL;
	protected $paymentMethodTable = NULL;
	protected $subscriptionTable = NULL;
	protected $transactionTable = NULL;
	protected $transactionRecieverTable = NULL;

	function __construct($sl) {
	   $this->service_locator = $sl;
	}


	public function getUserTable() {
		if (!$this->userTable) {
			$this->userTable = $this->service_locator->get('Application\Model\UserTable');
		}
		return $this->userTable;
	}

}
?>
