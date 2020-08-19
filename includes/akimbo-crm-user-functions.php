<?php 
/**
 *
 * Crm User Functions
 * 
 */

/*************
Reference list
**************


*/
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

/**
 * Add function akimbo_crm_get_available_user_orders,
 * then use below function to display only.
 * Get function can then be used in user class too
 */

function crm_select_available_user_orders($user_id, $name="item_id"){//$class_type = NULL <-- add check later
	global $wpdb;
	echo "<select name='".$name."'>";
	$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $user_id,) );
	$orders = $query->get_orders();
	foreach ( $orders as $order ) {
		$order_id = $order->get_id();;
		$items = $order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			$item_info = crm_get_item_available_passes($item_id, $order);
			if($item_info['available'] == true){
				?><option value="<?php echo $item_id;?>">
				<?php echo $item_info['name'].": ".$item_info['remaining']." ".$item_info['pass_type']." remaining";
				?></option><?php
			}		
		}
	}	
	?></select><?php
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


/**
 * Archived functions
 */

 /*function casual_return_available_sessions($user, $age = NULL){
	//get customer orders
	$statuses = ['completed','processing'];
	$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $user,'status' => $statuses) );
	//$statuses = ['completed','processing'];
	//$orders = wc_get_orders( ['limit' => -1, 'status' => $statuses] );
	$orders = $query->get_orders();
	
	if($age != NULL){
		$option = "akimbo_crm_".$age."_class_products";
		$products = get_option($option); //get product options
	}else{
		$products = array_merge(get_option('akimbo_crm_adult_class_products'), get_option('akimbo_crm_playgroup_class_products'));
	}
	
	foreach ( $orders as $order ) {
		$order_id = $order->get_id();
		$crm_order = wc_get_order($order_id);
		$items = $crm_order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			$product = $item_data['product_id'];
			//if($product=='1499' || $product=='1536'){
			if(in_array($product, $products)){
				$sessions_available = $item_data['quantity']*$item_data['pa_sessions'];
				$current_order['sessions_used'] = $item_data['sessions_used'];
				if(!$item_data['pa_sessions']){	//$current_pricing = false;
				} elseif($item_data['sessions_used'] >= $sessions_available){//updated >= 15/07/19
				} else {$current_pricing = true;
					$current_order_id = $item_id;
					$url = get_permalink( wc_get_page_id( 'myaccount' ) );
					$current_order['sessions'] = $item_data['pa_sessions'];
					$current_order['order_id'] = $order_id;
					$current_order['item_id'] = $item_id;
					$current_order['product_id'] = $item_data['product_id'];
					$current_order['available'] = $sessions_available;
					$current_order['quantity'] = $item_data['quantity'];
					$current_order['name'] = $item_data['name'];
					$current_order['used'] = $item_data['sessions_used'];
				}
			}
		}
	}
	return $current_order;
}*/

/**
 *
 * Option values for dropdown list. Usage: <select name="item_id"><?php crm_show_available_sessions($user_id); ?></select>
 * 
 */
/*function crm_show_available_sessions($user_id){
	$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $user_id,) );
	$orders = $query->get_orders();
	foreach ( $orders as $order ) {
		$order_id = $order->ID;
		$crm_order = wc_get_order($order_id);
		$items = $crm_order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			$sessions = $item_data['pa_sessions']*$item_data['qty'];//use this variable to calculate for multiple passes bought
			if(!$item_data['pa_sessions']){//$current_pricing = false;
			} elseif($item_data['sessions_used'] >= $sessions){//$current_pricing = false;//updated >= 15/07/19
			} else {
				$current_products[] = $item_data['product_id'];
				if (!$item_data['sessions_used']){$sessions_used = 0;$add_meta=true;}else{$sessions_used = $item_data['sessions_used'];}
				?><option value="<?php echo $item_id;?>"><?php echo $item_data['name'].": ".$sessions_used."/".$sessions." sessions";?></option><?php
			}
		}
	}	
}*/

//add_filter( 'wp_new_user_notification_email_admin', 'akimbo_crm_new_user_notification_email_admin', 10, 3 );
/*function akimbo_crm_new_user_notification_email_admin($wp_new_user_notification_email, $user, $blogname) {

    $user_count = count_users();

    $wp_new_user_notification_email['subject'] = sprintf('[%s] New user %s registered.',$blogname, $user->user_login);
    $wp_new_user_notification_email['message'] =
    sprintf( "%s has registerd to your blog %s.", $user->user_login, $blogname) .
"\n\n\r" . sprintf("This is user number %d!", $user_count['total_users']);

    return $wp_new_user_notification_email;

}*/