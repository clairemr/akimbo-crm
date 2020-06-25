<?php 

/**
 *
 * Akimbo CRM custom functions and shortcodes
 * 
 */

/*************
Reference list
**************

crm_simple_delete_button($table, $data_id, $data, $redirect, $display = NULL)//implement database deletion. $display=text on button
crm_date_selector($page, $tab): date dropdown that adds date value to url e.g. payroll, roster page
crm_date_setter_month($date)
crm_date_setter_week($date)

crm_get_user_email_list($user_list): get user emails from an array of user ids


akimbo_crm_redirect(): use at the end of admin posts to direct back to the right page. Values home, classes, bookings, business or custom urls may be passed


crm_weeks_remaining($item_id): returns weeks, weeks_used, qty, total and remaining for a given item id

*/

add_action( 'admin_post_crm_simple_delete_button_process', 'crm_simple_delete_button_process' );
add_action( 'admin_post_update_mailchimp_child', 'akimbo_crm_update_mailchimp_child' );


add_shortcode('termDates', 'akimbo_term_dates'); //[termDates]
add_shortcode('nextSemester', 'akimbo_next_semester'); //[nextSemester]






function crm_dropdown_selector($type, $page, $tab = NULL){//, $value = "View"
	?><form action="admin.php" method="get">
	<input type="hidden" name="page" value="<?php echo $page; ?>" /><?php 
	if($tab != NULL){
		echo "<input type='hidden' name='tab' value='".$tab."' />";
	}
	switch ($type) {
	    case "students":  	
	    	echo "Student: ";
	    	crm_student_dropdown("student"); 
	    break;
	    case "users": 
	    	echo "User: ";
	    	akimbo_user_dropdown("user"); 
	    break;
	}
	?><input type="submit" value="View"></form><?php
}


function crm_simple_delete_button($table, $data_id, $data, $redirect, $display = NULL){// "crm_class_list'", "list_id", "123", "/wp-admin/admin.php?page=akimbo-crm2"
	$display = ($display != NULL) ? $display : "Delete";
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="table" value="<?php echo $table; ?>">
	<input type="hidden" name="data_id" value="<?php echo $data_id; ?>">
	<input type="hidden" name="data" value="<?php echo $data; ?>">
	<input type="hidden" name="redirect" value="<?php echo $redirect; ?>">
	<input type="hidden" name="action" value="crm_simple_delete_button_process">
	<input type="submit" value="<?php echo $display; ?>">
	</form> <?php
}

function crm_simple_delete_button_process(){
	global $wpdb;
	$table = $wpdb->prefix.$_POST['table'];
	$data_id = $_POST['data_id'];
	$data = $_POST['data'];	
	$wpdb->delete( $table, array( $data_id => $data,) );
	$url = get_site_url().$_POST['redirect'];
	wp_redirect( $url ); 
	exit;	
}

function crm_date_selector($page = akimbo-crm, $tab=NULL){
	?><form action="admin.php" method="get">
	<input type="hidden" name="page" value="<?php echo $page; ?>" /><?php 
	if(isset($tab)){ ?> <input type="hidden" name="tab" value="<?php echo $tab; ?>" /> <?php } ?>
	
	<input type="date" name="date"><input type="submit" value="Change Date"></form><br/><?php
 }

function crm_date_setter_month($date){
	$date_setter['start'] = date('Y-m-01', strtotime($date));
	$date_setter['start_time'] = date('00:00 Y-m-01', strtotime($date));
	$date_setter['end'] = date('Y-m-t', strtotime($date));
	$date_setter['end_time'] = date('23:59 Y-m-t', strtotime($date));
	$date_setter['month'] = date("F", strtotime($date));
	$date_setter['number_of_days'] = date('t', strtotime($date));
	$date_setter['first_day'] = date('l', strtotime($date_setter['start']));
	
	$mnth = date('m', strtotime($date));
	if($mnth >= 12){
		$year = date('Y', strtotime($date)) + 1;
		$format = $year.'-01-01';
		$date_setter['next_month'] = date($format, strtotime($date));
	}else{
		$mth = date('m', strtotime($date)) + 1;
		$format = 'Y-'.$mth.'-01';
		$date_setter['next_month'] = date($format, strtotime($date));
	}

	if($mnth <= 1){
		$year = date('Y', strtotime($date)) - 1;
		$format = $year.'-12-01';
		$date_setter['previous_month'] = date($format, strtotime($date));
	}else{
		$mth = date('m', strtotime($date)) - 1;
		$format = 'Y-'.$mth.'-01';
		$date_setter['previous_month'] = date($format, strtotime($date));
	}
 	

	return $date_setter;
 }

function crm_date_setter_week($date){
 	$date_setter['week_start'] = date("Y-m-d 0:00", strtotime('monday this week', strtotime($date)));
	$date_setter['week_end'] = date("Y-m-d 23:59", strtotime('sunday this week', strtotime($date)));
	$date_setter['last_week_start'] = date("Y-m-d 0:00", strtotime('monday last week', strtotime($date)));
	$date_setter['last_week_end'] = date("Y-m-d 23:59", strtotime('sunday last week', strtotime($date)));
	$date_setter['start'] = $date;
	$date_setter['end'] = date("Y-m-d", strtotime('+7 days', strtotime($date)));
 	
	return $date_setter;
 }



/*function crm_simple_update_button($table, $data, $where, $content){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
	echo $content;
	?><input type="hidden" name="action" value="crm_simple_update_table"><input type="submit"></form><?php
}

add_action( 'admin_post_crm_simple_update_table', 'crm_simple_update_table' );

function crm_simple_update_table($table, $data, $where, $redirect){//'crm_students', array('list_id' => $_POST['id']),
	$table = $wpdb->prefix.$_POST['table'];
	$result = $wpdb->update( $table, $data, $_POST['where']);
	$message = ($result) ? "success" : "failure";
	$url = get_site_url().$redirect."&message=".$message;
	wp_redirect( $url ); 
	exit;
}*/



//Mailchimp integration



/**
 *
 * Crm Admin Functions
 * //Potentially should be a class
 */

/*************
Reference list
**************
crm_add_dashboard_widgets()//register dashboard widget
crm_dashboard_widget_function()//dashboard widget output


*/










//Not used? 14/1/20
/*function akimbo_crm_admin_student_details(){
	//akimbo_crm_show_all_classes();//code moved to crm-enrolment_functions
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_week($date);
	$header = "<h2>All classes from ".$crm_date['start']." - ".$crm_date['end']."</h2>";
	echo apply_filters('akimbo_crm_manage_business_header', $header);
	apply_filters('akimbo_crm_manage_business_date_selector', crm_date_selector("akimbo-crm3", "classes"));
	crm_class_list($crm_date['start'], $crm_date['end']); 
}*/


/**
 *
 * Plugin redirect, use at the end of admin posts to direct back to the right page. Values home, classes, bookings, business or custom urls may be passed
 * 
 */
function akimbo_crm_redirect($page){
	$site = get_site_url();
	if(isset($page)){
		switch ($page) {
			case "home":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm";
				break;
			case "classes":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm2";
				break;
			case "bookings":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm3";
				break;
			case "business":
				$url = $site."/wp-admin/admin.php?page=akimbo-crm4";
				break;
			default:
				$url = $site.$page;//allows custom urls to be passed
		}
	}else{
		$url = $site."/wp-admin/admin.php?page=akimbo-crm";		
	}

	wp_redirect( $url );
	exit;
}





function crm_get_user_email_list($user_list){//user emails from user ids
	foreach($user_list as $user){
		//$user = $student_edit->user_id;
		$user_info = get_userdata($user);
		//echo "<br/>Name: ". $user_info->first_name." ".$user_info->last_name;
		//echo '<br/>User roles: ' . implode(', ', $user_info->roles) . "\n";
		//echo '<br/>User ID: ' . $user_info->ID . "\n";
		echo $user_info->user_email.", ";
	}
}









function crm_nav_tab($page, $tab, $title, $active_tab){
	$url = "<a href='?page=".$page."&tab=".$tab."' ";
	$url .= $active_tab == $tab ? "class='nav-tab nav-tab-active'" : "class='nav-tab'";
	$url .= "'>".$title."</a>";
	/*?><a href="?page=akimbo-crm3&tab=classes" class="nav-tab <?php echo $active_tab == 'classes' ? 'nav-tab-active' : ''; ?>">All Classes</a><?php*/
	return $url;
}





function crm_weeks_remaining($item_id){
	global $wpdb;
	/*$weeks = wc_get_order_item_meta( $item_id, 'weeks', true );
	$weeks_used = wc_get_order_item_meta( $item_id, 'weeks_used', true );
	$qty = wc_get_order_item_meta( $item_id, '_qty', true );
	$total = $weeks * $qty;
	$remaining = $total - $weeks_used;*/
	$weeks_used = wc_get_order_item_meta( $item_id, 'weeks_used', true );
	if(!isset($weeks_used)){$weeks_used = 0;}
	$remaining['weeks'] = wc_get_order_item_meta( $item_id, 'weeks', true );
	$remaining['weeks_used'] = $weeks_used;
	$remaining['qty'] = wc_get_order_item_meta( $item_id, '_qty', true );
	$remaining['total'] = $remaining['weeks'] * $remaining['qty'];
	if($weeks_used >= 1){$remaining['remaining'] = $remaining['total'] - $weeks_used;}else{$remaining['remaining'] = $remaining['total'];}
	
	return $remaining;
}







function akimbo_term_dates($format = 'echo', $date = NULL){
	global $wpdb;
	if($date == NULL){$date = current_time('Y-m-d-h:ia');}
	$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$date' ORDER BY semester_end ASC LIMIT 1");//get current term
	$curTerm = $semester->semester_slug;
	$term = substr($curTerm, 1, 1);
	$start_date = date("l jS F", strtotime($semester->semester_start));
	$end_date = date("l jS F", strtotime($semester->semester_end));
	if($format == 'return'){
		$content = array('slug' => $curTerm, 'start' => $semester->semester_start, 'end' => $semester->semester_end);
	}else{
		$content = "Term ".$term.": ".$start_date." - ".$end_date;
	}
	return $content;
}

function akimbo_previous_semester($current_slug){
	global $wpdb;
	$semester_id = $wpdb->get_var("SELECT semester_id FROM {$wpdb->prefix}crm_semesters WHERE semester_slug = '$current_slug'");//get previous term
	$previous_id = $semester_id - 1;
	if($previous_id >= 1){
		$semester = $wpdb->get_var("SELECT semester_slug FROM {$wpdb->prefix}crm_semesters WHERE semester_id = '$previous_id'");//get previous term
	}else{$semester = "No earlier semesters";}
	
	return $semester;
}

function akimbo_next_semester(){
	global $wpdb;
	$today = current_time('Y-m-d-h:ia');
	$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_start >= '$today' ORDER BY semester_start ASC LIMIT 1");//get next term
	$curTerm = $semester->semester_slug;
	$term = substr($curTerm, 1, 1);
	$start_date = date("l jS F", strtotime($semester->semester_start));
	$end_date = date("l jS F", strtotime($semester->semester_end));
	$content = "Term ".$term.", ".date("Y", strtotime($semester->semester_start)).": ".$start_date." - ".$end_date;
	
	return $content;
}


function akimbo_crm_mailchimp_connection($email = NULL){
	$connection['apikey'] = "4dc5e76991af0d9ead66a1934a7261fe-us3";
	if($email != NULL){$connection['userid'] = md5( strtolower( $email ) );}
	$connection['auth'] = base64_encode( 'user:'. $connection['apikey'] );
	
	$connection['server'] = "us3";
	//$listid = "4e3828afd5"; //Welcome to Circus Akimbo 
	$connection['listid'] = "6129e84ea3"; //Live list
	
	//$list_id = get_option("akimbo_crm_adult_class_products");//eventually add option

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
 *
 * Potentially outdated or broken
 * 
 */

/*************
Reference list
**************


*/


/*function register_crm_admin_css(){
	//get_stylesheet_directory_uri for childtheme, get_template_directeory_uri otherwise
	$src = get_stylesheet_directory_uri()."/css/admin-css.css";
	$handle = "crmAdminCss";
	wp_register_script($handle, $src);
	wp_enqueue_style($handle, $src, array(), false, false);
}*/

add_action( 'login_enqueue_scripts', 'akimbo_crm_login_logo' );

function akimbo_crm_login_logo() { ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/images/site-login-logo.png);
		height:65px;
		width:320px;
		background-size: 320px 65px;
		background-repeat: no-repeat;
        	padding-bottom: 30px;
        }
    </style>
<?php }