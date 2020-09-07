<?php
/**
 * Mailchimp integration on student details page
 */
function akimbo_crm_student_details_mailchimp_integration($student){
	echo "<h2>Mailchimp integration: ";
	$mailchimp = $student->update_mailchimp();//updates mailchimp and returns values
	if($mailchimp['subscribed'] == false){
		echo "<small>User not subscribed</small></h2>";
		echo "CRM start: ".date("g:ia, l jS M Y", strtotime($student->first_class()->session_date))."<br/>";
		echo "CRM most recent: ".date("g:ia, l jS M Y", strtotime($student->last_class()->session_date))."<br/>";
	}else{
		echo "<small>".$mailchimp['email']."</small></h2>";
		echo "Mailchimp start: ".date("l jS M Y", strtotime($mailchimp['MCstart']))."<br/>";
		echo "Mailchimp most recent: ".date("l jS M Y", strtotime($mailchimp['MCend']))."<br/>";
	}	
}

//add_action

/**
 * Display function, admin page
 */
function akimbo_crm_manage_mailchimp_integration($page, $tab){
	global $wpdb;
	echo "<h2>Mailchimp: <small>Send emails to specific segments of the mailing list, and ensure the included student names are correct</small></h2>";
	$age = (isset($_GET['age'])) ? $_GET['age'] : "kids" ;
	$status = (isset($_GET['status'])) ? $_GET['status'] : 'current' ;
	$semester = akimbo_term_dates('return', current_time('Y-m-d-h:ia'));
	$semester = (isset($_GET['semester']))? $_GET['semester'] : $semester['slug'];
	?><form action="admin.php" method="get"><input type="hidden" name="page" value="<?php echo $page; ?>" /><input type="hidden" name="tab" value="<?php echo $tab; ?>" />
	Age: <select name="age"><option value="<?php echo $age;?>" ><?php echo $age;?></option><option>****</option><option value="kids">Kids</option><option value="adult">Adult</option><option value="playgroup">Playgroup</option><option value="<?php echo NULL;?>">All</option></select>
	Status: <select name="status"><option value="<?php echo $status;?>" ><?php echo $status;?></option><option>****</option><option value="current">Current</option><option value="not_returning">Not Returning</option><option value="all">All</option></select>
	Semester: <input type="text" name="semester" value="<?php echo $semester; ?>">
	<input type="submit" value="Update Student List"></form><?php

	$students = akimbo_crm_get_students($age, $status, $semester);
	if($students){
		foreach($students as $student){
			$student_name = ($student->get_student_info()->student_rel == "user") ? "you" : $student->first_name();
			
			if($student->contact_email() != NULL){
				//echo $student->contact_email()."<br/>";
				//$subscriber_data = akimbo_crm_mailchimp_get_all_merge_fields($student->contact_email());
				//removed because it was running too slow and timing out
				//if(is_array($subscriber_data)){
					if(!isset($MCusers[$student->contact_email()])){
						$MCusers[$student->contact_email()] = $student_name;
					}else{
						$registered_students = $MCusers[$student->contact_email()];
						if(strstr( $registered_students, '&' )){//already have at least 2 students, add student name to start
							$MCusers[$student->contact_email()] = $student_name.", ".$MCusers[$student->contact_email()];
						}else{
							$MCusers[$student->contact_email()] = $MCusers[$student->contact_email()]." & ".$student_name;
						}
					}
				//}
			}else{
				echo $student->student_id." NULL <br/>";
			}
			
			
		}
	}else{echo "No Students found";}


	if($MCusers){
		echo "Subscribers: ";
		foreach($MCusers as $email => $value){
			echo $email.", ";
		}
		/*echo "<br/>Students: ";
		foreach($MCusers as $email => $value){
			echo $value.", ";
		}*/
		$user_array = base64_encode(serialize($MCusers));;
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input type="hidden" name="users" value="<?php echo $user_array; ?>">
		<input type="hidden" name="referral_url" value="<?php echo get_site_url()."/wp-admin/admin.php?page=".$page."&tab=".$tab; ?>">
		<input type="hidden" name="action" value="update_mailchimp_child">
		<br/><input type="submit" value="Update Student Names"></form><?php
	}
}

/**
 * Actions
 */

function akimbo_crm_mailchimp_connection($email = NULL){
	/*$connection['apikey'] = "4dc5e76991af0d9ead66a1934a7261fe-us3";
	if($email != NULL){$connection['userid'] = md5( strtolower( $email ) );}
	$connection['auth'] = base64_encode( 'user:'. $connection['apikey'] );
	$connection['server'] = "us3";
	//$listid = "4e3828afd5"; //Welcome to Circus Akimbo 
	$connection['listid'] = "6129e84ea3"; //Live list*/
	$connection['apikey'] = get_option('akimbo_crm_mailchimp_apikey');
	$connection['auth'] = base64_encode( 'user:'. $connection['apikey'] );
	$connection['listid'] = get_option('akimbo_crm_mailchimp_list_id');
	$connection['server'] = get_option('akimbo_crm_mailchimp_server');
	if($email != NULL){$connection['userid'] = md5( strtolower( $email ) );}

	return $connection;
}

//hook to create user function. Works for manually added users
add_action( 'user_register', 'akimbo_crm_mailchimp_subscribe_new_user', 10, 1 );
function akimbo_crm_mailchimp_subscribe_new_user($user_id){
	//https://metamug.com/article/php-mailchimp-api-add-subscriber-email.html	
	$merge_fields = array();
	if(isset($_POST["first_name"])){$merge_fields['FNAME'] = $_POST["first_name"];}
	if(isset($_POST["last_name"])){$merge_fields['LNAME'] = $_POST["last_name"];}
	if (isset($_POST['email'])){
		$data = array(
			"email_address" => $_POST["email"], 
			"status" => "subscribed", 
		);
		if($merge_fields){$data['merge_fields'] = $merge_fields;}
	}
	$connection = akimbo_crm_mailchimp_connection();
	$ch = curl_init('https://'.$connection['server'].'.api.mailchimp.com/3.0/lists/'.$connection['listid'].'/members/');
	curl_setopt_array($ch, array(
	    CURLOPT_POST => TRUE,
	    CURLOPT_RETURNTRANSFER => TRUE,
	    CURLOPT_HTTPHEADER => array(
	        'Authorization: apikey '.$connection['apikey'],
	        'Content-Type: application/json'
	    ),
	    CURLOPT_POSTFIELDS => json_encode($data),
	));
	$response = curl_exec($ch);
}

add_action( 'admin_post_update_mailchimp_child', 'akimbo_crm_update_mailchimp_child' );

function akimbo_crm_update_mailchimp_child(){//$users = array($email => $value,)
	$users = unserialize(base64_decode($_POST['users']));//use base64 decode, https://davidwalsh.name/php-serialize-unserialize-issues
	foreach($users as $email => $value){
		akimbo_crm_mailchimp_update_merge_field('CHILD', $value, $email);
	}
	if($_POST['referral_url']){$url = $_POST['referral_url'];}else{$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm";}
	wp_redirect( $url ); 
	exit;	
}

function akimbo_crm_mailchimp_update_merge_field($field, $value, $email){
	global $wpdb;
	$connection = akimbo_crm_mailchimp_connection($email);
	
	$data = array(
	'apikey'        => $connection['apikey'],
	'email_address' => $email,
	'merge_fields'  => array( //add merge fields here to update them
		$field => $value
		)
	);
	$json_data = json_encode($data);
	//Curl Request
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://'.$connection['server'].'.api.mailchimp.com/3.0/lists/'.$connection['listid'].'/members/' . $connection['userid']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Basic '. $connection['auth']));
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");//this is the important bit!! GET/PATCH
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
	$result = curl_exec($ch);
	curl_close($ch);
}
// ///3.0/lists/9e67587f52/members/
/*function akimbo_crm_mailchimp_get_merge_field($field, $email){
	global $wpdb;
	akimbo_crm_mailchimp_connection($email);
	
	
	//Curl Request, https://github.com/actuallymentor/MailChimp-API-v3.0-PHP-cURL-example/blob/master/mc-API-connector.php
	$server = "us3";
	$listid = "6129e84ea3"; //Live list //$listid = "4e3828afd5"; //Welcome to Circus Akimbo 
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://'.$server.'.api.mailchimp.com/3.0/lists/'.$listid.'/members/' . $userid);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Basic '. $auth));
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");//this is the important bit!! GET/PATCH(update)/POST(add new)/PUT(create or update)/DELETE
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
	$result = curl_exec($ch);
	curl_close($ch);
	
	$obj = json_decode($result, true);
	if(!isset($obj['merge_fields'])){$value = "User not subscribed";
	} else{
		$merge_fields = $obj['merge_fields'];
		$value = $merge_fields[$field];
	}

	return $value;
}*/

function akimbo_crm_mailchimp_get_all_merge_fields($email, $field = NULL){
	global $wpdb;
	$connection = akimbo_crm_mailchimp_connection($email);
	$data = array(
	'apikey'        => $connection['apikey'],
	'email_address' => $email,
	);
	$json_data = json_encode($data);

	//Curl Request, https://github.com/actuallymentor/MailChimp-API-v3.0-PHP-cURL-example/blob/master/mc-API-connector.php
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://'.$connection['server'].'.api.mailchimp.com/3.0/lists/'.$connection['listid'].'/members/' . $connection['userid']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Basic '. $connection['auth']));
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");//this is the important bit!! GET/PATCH(update)/POST(add new)/PUT(create or update)/DELETE
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
	$result = curl_exec($ch);
	curl_close($ch);
	
	$obj = json_decode($result, true);
	if(!isset($obj['merge_fields'])){$merge_fields = "User not subscribed";
	} else{
		$merge_fields = $obj['merge_fields'];
		$result = ($field != NULL) ? $merge_fields[$field] : $merge_fields;	
	}

	return $result;
	
	/*
	EXAMPLE USE
	$details = akimbo_crm_mailchimp_get_all_merge_fields($email);
	echo "<br/>TEST: ".$details['ENDDATE'];
	*/
	
}

