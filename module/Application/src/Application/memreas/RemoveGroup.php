<?php
    /*
    * Remove group
    * @params:
    * @Return
    */
    namespace Application\memreas;

    use Zend\Session\Container;
    use Application\Model\MemreasConstants;
    use Application\memreas\AWSManagerSender;
    use Application\Entity\Group;
    use Application\Entity\FriendGroup;

    class RemoveGroup{
        protected $message_data;
        protected $memreas_tables;
        protected $service_locator;
        protected $dbAdapter;
        public function __construct($message_data, $memreas_tables, $service_locator) {
            error_log ( "Inside__construct..." );
            $this->message_data = $message_data;
            $this->memreas_tables = $memreas_tables;
            $this->service_locator = $service_locator;
            $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
        }


        public function exec($frmweb = false, $output = '') {
            $error_flag = 0;
            $message = '';
            if (empty ( $frmweb )) {
                $data = simplexml_load_string ( $_POST ['xml'] );
            } else {
                $data = json_decode ( json_encode ( $frmweb ) );
            }

            $group_id = $data->removegroup->group_id;

            //Check if group exist or not
            $group_query = $this->dbAdapter->createQueryBuilder();
            $group_query->select('g')
                ->from('Application\Entity\Group', 'g')
                ->where('g.group_id = ?1')
                ->setParameter(1, $group_id);
            $result = $group_query->getQuery()->getResult();

            if (empty($result)){
                $status = 'Failure';
                $message = 'This group does not exist';
            }
            else{

                //Remove group's friend
                $query_str = "DELETE FROM Application\Entity\FriendGroup fg WHERE fg.group_id = '$group_id'";
                $query = $this->dbAdapter->createQuery($query_str);
                $result = $query->getResult();

                //Remove group
                $query_str = "DELETE FROM Application\Entity\Group g WHERE g.group_id = '$group_id'";
                $query = $this->dbAdapter->createQuery($query_str);
                $result = $query->getResult();

                $status = 'Success';
                $message = 'Your group has been removed.';
            }

            if ($frmweb) {
                return $output;
            }
            header ( "Content-type: text/xml" );
            $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
            $xml_output .= "<xml>";
            $xml_output .= "<removegroupresponse>";
            $xml_output .= "<status>{$status}</status>";
            if (isset($message)) $xml_output .= "<message>{$message}</message>";
            $xml_output .= "</removegroupresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

?>
