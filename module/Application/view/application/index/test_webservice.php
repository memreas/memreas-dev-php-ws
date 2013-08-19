<?php
use Application\Model\MemreasConstants;


//require_once 'Application\Index\ws\config.php';
//$vendor_autoloader = dirname(__DIR__) . '/' . 'vendor/autoload.php';

//require $vendor_autoloader;
//require 'memreascache.php';

use Guzzle\Http\Client;

function fetchXML($url, $action, $xml) {
	$guzzle = new Client();

	$request = $guzzle->post(
		$url, 
		null, 
		array(
		'action' => $action,
		//'cache_me' => true,
    	'xml' => $xml
    	)
	);
	$response = $request->send();
	return $data = $response->getBody(true);
}


if(isset($_POST['formSubmit']) == "Submit")
{
	$errorMessage = null;
	$uname = "";
	$uid = "";
	$eventid = "";
	$deviceid = "";
	$mediaid = "";
	
	if(isset($_POST['action']))
	{
		$varAction = $_POST['action'];
	} else {
		$errorMessage .= "<li>No action specified</li>";
//echo "action is NOT set....<BR>";
	}
	if(isset($_POST['uname']))
	{
		$uname = $_POST['uname'];
	}
	if(isset($_POST['uid']))
	{
		$uid = $_POST['uid'];
	}
	if(isset($_POST['eventid']))
	{
		$eventid = $_POST['eventid'];
	}
	if(isset($_POST['deviceid']))
	{
		$deviceid = $_POST['deviceid'];
	}
	if(isset($_POST['mediaid']))
	{
		$mediaid = $_POST['mediaid'];
	}
	if(empty($errorMessage)) 
	{
		$errorMessage .= "<li>action ----> " . $varAction . "</li>"; 
		$data = $varAction;
	}

	if(!empty($varAction)) 
	{
		//Test ListPhotos;
		//$url = "http://192.168.1.8/eventapp_zend2.1/webservices/index_memreas.php";
		//$url = "http://192.168.1.8/eventapp_zend2.1/webservices/index.php";
		//MemreasConstants::URL = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index.php";
		$action = $varAction;

		if ($varAction == 'login') {
			$xml = "
			
			
			<xml><login><username>$uid</username><password>hussain</password></login></xml>

			
			";
		} else if ($varAction == 'addevent') {
			$xml = "<xml><addevent><user_id>$uid</user_id><event_name>Event 1</event_name><event_date>17/05/2013</event_date><event_location>East Meadow</event_location><event_from>17/05/2013</event_from><event_to>17/06/2013</event_to><is_friend_can_add_friend>yes</is_friend_can_add_friend><is_friend_can_post_media>yes</is_friend_can_post_media><event_self_destruct>17/07/2013</event_self_destruct><is_public>1</is_public></addevent></xml>";
		} else if ($varAction == 'checkusername') {
			$xml = "<xml><checkusername><username>$uname</username></checkusername></xml>";
		} else if ($varAction == 'creategroup') {
			$xml = "<xml><creategroup><group_name>memreas</group_name><user_id>$uid</user_id><friends><friend><friend_name>some_friend</friend_name><network_name>facebook</network_name><profile_pic_url><![CDATA[http://www.memreas.com/]]></profile_pic_url></friend></friends></creategroup></xml>";
		} else if ($varAction == 'countlistallmedia') {
			$xml = "<xml><countlistallmedia><limit>15</limit><event_id>$eventid</event_id><user_id>$uid</user_id><device_id>$deviceid</device_id></countlistallmedia></xml>";
		} else if ($varAction == 'editevent') {
			$xml = "<xml><editevent><user_id>$uid</user_id><event_id>$eventid</event_id><event_name>Event 1</event_name><event_date>22/02/2013</event_date><event_location>Ahmedabad</event_location><event_from>22/02/2013</event_from><event_to>28/02/2013</event_to><is_friend_can_add_friend>yes</is_friend_can_add_friend><is_friend_can_post_media>no</is_friend_can_post_media><event_self_destruct>02/03/2013</event_self_destruct></editevent></xml>";
		} else if ($varAction == 'listphotos') {
			$xml = "<xml><listphotos><userid>$uid</userid><deviceid>$deviceid</deviceid></listphotos></xml>";
		} else if ($varAction == 'listallmedia') {
			$xml = " <xml><listallmedia><event_id>$eventid</event_id><user_id>$uid</user_id><device_id></device_id><limit>2</limit><page>1</page></listallmedia></xml>";
		} else if ($varAction == 'listgroup') {
			$xml = "<xml><listgroup><user_id>$uid</user_id></listgroup></xml>";
		} else if ($varAction == 'viewallfriends') {
			$xml = "<xml><viewallfriends><user_id>$uid</user_id></viewallfriends></xml>";
		} else if ($varAction == 'viewevents') {
			$xml = "<xml><viewevent><user_id>$uid</user_id><is_my_event>1</is_my_event><is_friend_event>0</is_friend_event><is_public_event>0</is_public_event><page>2</page><limit>2</limit></viewevent></xml>";
		} else if ($varAction == 'viewmediadetails') {
			$xml = "<xml><viewmediadetails><media_id>$mediaid</media_id></viewmediadetails></xml>";

		}

		echo '<table border="1"><tr><td>Input xml ------></td><td>' . htmlentities( $xml ) . '</td></tr></table>';
		echo '<table border="1"><tr><td>Output xml ------></td><td>' . htmlentities( fetchXML(MemreasConstants::URL, $action, $xml) ) . '</td></tr></table>';

		error_log($data, 0);
		//echo( htmlentities( fetchXML(MemreasConstants::URL, $action, $xml) ) );
	}
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"> 
<html>
<head>
<title>My Form</title>

<!-- Fetch the user_id -->
<script type="text/javascript">
var user_id = "<?php echo $user_id; ?>";
	//Store Card
    $(document).ready(function () {
        $("#btnOK").click(onBtnCallClicked);
    });
    function onBtnCallClicked() {
alert("onBtnCallClicked fired");
        jQuery.fetchWS();
    }


    $(function() {
	    $( "#tabs" ).tabs();
	});
	
</script>

</head>

<body>


	<?php
		if(!empty($errorMessage)) 
		{
			echo("<p>There was an error with your form:</p>\n");
			echo("<ul>" . $errorMessage . "</ul>\n");
		} 
		if(!empty($varAction)) 
		{
			echo("<p>DATA BELOW ....</p>\n");
			echo("<ul>" . $data . "</ul>\n");
		} 
	?>

<P>
	<form action="/index/ws" method="post">
		<H3>Enter Data Here</H3>
			<table border="1">
				<tr>
					<td>User Name:</td>
					<td><input type="text" name="uname"></td>
				</tr>
				<tr>
					<td>User Id:</td>
					<td><input type="text" name="uid"></td>
				</tr>
				<tr>
					<td>Event Id:</td>
					<td><input type="text" name="eventid"></td>
				</tr>
				<tr>
					<td>Device Id:</td>
					<td> <input type="text" name="deviceid"></td>
				</tr>
				<tr>
					<td>Media Id:</td>
					<td><input type="text" name="mediaid"></td>
				</tr>
				<tr>
					<td>
						<select name="action">

  							<option>addevent</option>  			
	  						<option>checkusername</option>
  							<option>countlistallmedia</option>
  							<option>creategroup</option>  					
 				 			<option>editevent</option>  	
 				 			<option>listgroup</option>  			
						  	<option>listphotos</option>
  							<option>listallmedia</option>
  							<option>viewallfriends</option>
  							<option>viewevents</option>
 				 			<option>viewmediadetails</option>  			


<!-- to be updated
    						<option>addcomments</option>            
    						<option>addfriendtoevent</option>             
    						<option>addmediaevent</option>             
    						<option>addmediatoevent</option>             
    						<option>addphoto</option>
    						<option>changepassword</option>
    					   	<option>countviewallfriends</option>             
    					   	<option>countviewevent</option>             
    						<option>deletephoto</option>     
    					    <option>download</option>             
    					    <option>forgetpassword</option>
    					    <option>likemedia</option>             
    					    <option>login</option>
    					   	<option>logout</option>
    					   	<option>mediainappropriate</option>             
    					    <option>registration</option>
    					    <option>uploadmedia</option>     
    					   	<option>uploadphoto</option>     

alphabetical order below

    						<option>addcomments</option>            
    						<option>addevent</option>             
    						<option>addfriendtoevent</option>             
    						<option>addmediaevent</option>             
    						<option>addmediatoevent</option>             
    						<option>addphoto</option>
    						<option>changepassword</option>
    						<option>checkusername</option>             
    					    <option>countlistallmedia</option>             
    					   	<option>countviewallfriends</option>             
    					   	<option>countviewevent</option>             
    					    <option>creategroup</option>             
    						<option>deletephoto</option>     
    					    <option>download</option>             
    					   	<option>editevent</option>             
    					    <option>forgetpassword</option>
    					    <option>listallmedia</option>             
    					    <option>listgroup</option>             
    					    <option>likemedia</option>             
    					    <option>listphotos</option>
    					    <option>login</option>
    					   	<option>logout</option>
    					   	<option>mediainappropriate</option>             
    					    <option>registration</option>
    					    <option>uploadmedia</option>     
    					   	<option>uploadphoto</option>     
    					   	<option>viewallfriends</option>             
    					    <option>viewevents</option>             
    					    <option>viewmediadetails</option>             
-->


						</select>
					</td>
					<td><input type="submit" name="formSubmit" value="Submit" /></td>
				</tr>
		</table>	
	</form>

<P>

<?php
	$uname = "jmeah1";
	$uid = "206158e0-c690-11e2-a30b-123139103cf6";
	$eventid = "6c69f7e0-c6d8-11e2-a30b-123139103cf6";
	$deviceid = "07763978-c4be-11e2-a30b-123139103cf6_A4A9163C-BB8B-43C0-8C18-D21D81BEE539";
	$mediaid = "07e3ba8e-c4be-11e2-a30b-123139103cf6";
?>

		<H3>Sample input xml by action for jmeah1</H3>
		<table border="1">
			<tr>
				<td>Action:</td>
				<td>listphotos</td>
				<td><?php echo ( htmlentities("<xml><listphotos><userid>$uid</userid><deviceid>$deviceid</deviceid></listphotos></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>countlistallmedia</td>
				<td><?php echo ( htmlentities("<xml><countlistallmedia><limit>15</limit><event_id>$eventid</event_id><user_id>$uid</user_id><device_id>$deviceid</device_id></countlistallmedia></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>listallmedia</td>
				<td><?php echo ( htmlentities("<xml><listallmedia><event_id></event_id><user_id>$uid</user_id><device_id></device_id><limit>2</limit><page>1</page></listallmedia></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>viewallfriends</td>
				<td><?php echo ( htmlentities("<xml><viewallfriends><user_id>$uid</user_id></viewallfriends></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>checkusername</td>
				<td><?php echo ( htmlentities("<xml><checkusername><username>jmeah1</username></checkusername></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>viewevent</td>
				<td><?php echo ( htmlentities("<xml><viewevent><user_id>$uid</user_id><is_my_event>1</is_my_event><is_friend_event>0</is_friend_event><is_public_event>0</is_public_event><page>2</page><limit>2</limit></viewevent></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>listgroup</td>
				<td><?php echo ( htmlentities("<xml><listgroup><user_id>$uid</user_id></listgroup></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>viewmediadetails</td>
				<td><?php echo ( htmlentities("<xml><viewmediadetails><media_id>$mediaid</media_id></viewmediadetails></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>addevent</td>
				<td><?php echo ( htmlentities("<xml><addevent><user_id>$uid</user_id><event_name>Event 1</event_name><event_date>17/05/2013</event_date><event_location>East Meadow</event_location><event_from>17/05/2013</event_from><event_to>17/06/2013</event_to><is_friend_can_add_friend>yes</is_friend_can_add_friend><is_friend_can_post_media>yes</is_friend_can_post_media><event_self_destruct>17/07/2013</event_self_destruct><is_public>1</is_public></addevent></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>editevent</td>
				<td><?php echo ( htmlentities("<xml><editevent><user_id>$uid</user_id><event_id>$eventid</event_id><event_name>Event 1</event_name><event_date>22/02/2013</event_date><event_location>Ahmedabad</event_location><event_from>22/02/2013</event_from><event_to>28/02/2013</event_to><is_friend_can_add_friend>yes</is_friend_can_add_friend><is_friend_can_post_media>no</is_friend_can_post_media><event_self_destruct>02/03/2013</event_self_destruct></editevent></xml>") ); ?></td>
			</tr>
			<tr>
				<td>Action:</td>
				<td>creategroup</td>
				<td><?php echo ( htmlentities("<xml><creategroup><group_name>memreas</group_name><user_id>$uid</user_id><friends><friend><friend_name>some_friend</friend_name><network_name>facebook</network_name><profile_pic_url><![CDATA[http://www.memreas.com/]]></profile_pic_url></friend></friends></creategroup></xml>") ); ?></td>
			</tr>
		</table>
</body>
</html>

		
