<?php //Akimbo Crm User Functions

/**
 * Admin page user information
 */

function akimbo_crm_admin_user_info($user){
	echo '<h3>User: ' .$user->metadata['first_name'][0]." ".$user->metadata['last_name'][0]." (". $user->metadata['nickname'][0].")</h3>";
	echo "Phone: ".$user->metadata['billing_phone'][0];
	echo "<br/>Email: ".$user->metadata['billing_email'][0];
	echo "<br/>Orders: ".$user->metadata['_order_count'][0];
	echo "<br/>Available Passes: ";
	akimbo_crm_display_user_orders($user->get_id());
	echo "<h4>Students</h4>";
	$students = $user->get_students("list");
	$display = ($students == false) ? "No students found" : $students;
	echo $display."<br/><hr><br/>";
	echo $user->user_admin_link("Edit user on Wordpress", "Wordpress");
}

/**
 * Replaces crm_select_available_user_orders
 */
function akimbo_crm_display_user_orders($user_id, $age = NULL, $name = "item_id"){
	$user = new Akimbo_Crm_User($user_id);
	$orders = $user->get_available_user_orders($age, false);
	if($orders == false){
		$message = ($age != NULL) ? "No available ".$age." passes" : "No available orders";
		echo $message;
	}else{
		echo "<select name='".$name."'>";
		foreach($orders as $order){
			echo "<option value='".$order['item_id']."'>".$order['order_id']." ".$order['name'].": ";
			echo $order['remaining']."/".$order['passes']." ".$order['pass_type']." remaining"."</option>";
		}
		echo "</select>";
		//var_dump($order['item']);
	}
}

function akimbo_crm_display_user_order_account($user, $age = NULL){
	$order = $user->get_available_user_orders($age, true);
	if(isset($order)){
		if($order['subscription'] == true){echo "Membership active: ";}
		if($order['remaining'] >= 1){
			echo "You have ".$order['remaining']."/".$order['passes']." ".$age." classes remaining";
			echo ", expiring on ".date(" l jS F Y", strtotime($order['expiry'])); 
			echo ". ".$order['url'];
		}
		$product_id = (isset($order['product_id'])) ? $order['product_id'] : 0;
		$item_id = ($order['remaining'] >= 0) ? $order['item_id'] : 0;
	}else{
		echo "<br/><i>Please visit the <a href='".get_permalink( wc_get_page_id( 'shop' ) )."'>store</a> to purchase a class pass</i>";
	}
}


add_shortcode('userList', 'akimbo_user_dropdown_shortcode'); 
add_filter( 'wp_new_user_notification_email', 'akimbo_crm_new_user_notification_email', 10, 3 );
add_action('user_register', 'akimbo_crm_auto_add_user_as_student');
add_action('woocommerce_created_customer', 'akimbo_crm_auto_add_user_as_student_woocommerce'); 
add_action( 'admin_post_user_add_new_student', 'user_add_new_student_process' );

function crm_user_name_from_id($user_id){
	$user_info = get_userdata($user_id);
	return $user_info->display_name;
}

/**
 * Don't think this function is used anywhere??
 */
function akimbo_user_dropdown_shortcode($var){
	extract(shortcode_atts(array('name' => '', 'current' => '', 'role' => ''), $var));
	akimbo_user_dropdown($name, $current);
}

function akimbo_user_dropdown($name, $current = NULL){ 
	global $wpdb;
	echo "<select name= '".$name."'>";
	if($current != NULL){
		$user_info = get_userdata($current);
		echo "<option value='".$current."'>".$user_info->display_name."</option>";
	}else{
		echo "<option value='1'><i>Circus Akimbo</i></option>";
	}
	$users = get_users();
	foreach ($users as $user){ ?>
		<option value="<?php echo $user->ID;?>"><?php echo $user->display_name;?></option><?php 
	}
	echo "</select>";
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



function akimbo_crm_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
	$message = sprintf(__('Hi %s,'), $user->display_name) . "\r\n\r\n";
	$custom_message = get_option('crm_new_user_message');
	$message .= __($custom_message). "\r\n\r\n";
    $message .= __('To login, please set your password from the link below, using this email address as your user name.')."\r\n\r\n";
	$message .= network_site_url("/wp-login.php?action=lostpassword") . "\r\n\r\n";
	$message .= __('If you have any questions please let us know!') . "\r\n\r\n";
	$message .= __('Regards,') . "\r\n\r\n";
	$message .= $blogname."\r\n\r\n";

    $wp_new_user_notification_email['message'] = $message;

    return $wp_new_user_notification_email;

}

function akimbo_crm_auto_add_user_as_student($user_id) {//automatically adds a new student when user registers
	global $wpdb;
	//if($_POST['role'] == 'customer'){} <--maybe add this later
	if(isset($_POST['first_name'])){
		$first_name = $_POST['first_name'];
	}else{
		$user_info = get_userdata($user_id);
      	$first_name = $user_info->first_name;
	}
	$table = $wpdb->prefix.'crm_students';
	$data = array(
		'user_id' => $user_id,
		'student_rel' => 'user',
		'student_firstname' => $first_name,
		);
	
	$wpdb->insert($table, $data);
}

/**
 * These functions may now be redundant from user class
 */
function user_add_new_student_process(){
	global $wpdb;
	$first_name = $customer->get_first_name();
	$table = $wpdb->prefix.'crm_students';
	$data = array(
		'user_id' => $_POST['user_id'],
		'student_firstname' => $_POST['first_name'],
		);
	$wpdb->insert($table, $data);
	$url = (isset($_POST['url'])) ? $_POST['url'] : akimbo_crm_permalinks("students");
	wp_redirect( $url ); 
	exit;
}

//function akimbo_crm_auto_add_user_as_student_woocommerce($customer_id, $new_customer_data, $password_generated) {//automatically adds a new student when user registers
function akimbo_crm_auto_add_user_as_student_woocommerce($customer_id) {//removed extra fields on 9/2/2020, throwing error because only 1 field passed
	global $wpdb;
	//if($_POST['role'] == 'customer'){} <--maybe add this later
	//$user = get_user_by( 'id', $customer_id );
	$customer = new WC_Customer( $customer_id );
	$first_name = $customer->get_first_name();
	$table = $wpdb->prefix.'crm_students';
	$data = array(
		'user_id' => $customer_id,
		'student_rel' => 'user',
		'student_firstname' => $first_name,
		);
	$wpdb->insert($table, $data);
}