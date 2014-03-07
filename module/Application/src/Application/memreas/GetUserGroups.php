<?php
/*
* Get user's group service
* @params: user_id Provide user id to get back detail
* @Return User group detail
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Group;

class  GetUserGroups{
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
        // $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
    }

    /*
     *
     */
    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty ( $frmweb )) {
            $data = simplexml_load_string ( $_POST ['xml'] );
        } else {

            $data = json_decode ( json_encode ( $frmweb ) );
        }
        $user_id = trim ( $data->getusergroups->user_id );

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select ( 'g' );
        $qb->from ( 'Application\Entity\Group', 'g' );
        $qb->where ( "g.user_id=?1" );
        $qb->setParameter(1, $user_id);
        $result_groups = $qb->getQuery ()->getResult ();
        if (empty($result_groups)) {
            $status = "Failure";
            $message = "You have no group at this time.";
        } else {
            $status = 'Success';
            $output .= '<user_id>' . $result_groups[0]->user_id . '</user_id>';
            $output .= '<groups>';
            foreach ($result_groups as $group){
                $output .= '<group>';
                $output .= '<group_id>' . $group->group_id . '</group_id>';
                $output .= '<group_name>' . $group->group_name . '</group_name>';
                $output .= '<create_date>' . $group->create_date . '</create_date>';
                $output .= '<update_date>' . $group->update_date . '</update_date>';
                $output .= '</group>';
            }
            $output .= '</groups>';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<getusergroupsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</getusergroupsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
