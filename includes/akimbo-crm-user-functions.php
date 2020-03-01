<?php 
/**
 *
 * Crm User Functions
 * 
 */

/*************
Reference list
**************

casual_return_available_sessions($user_id): For a given user, returns sessions, order_id, item_id, product_id, available (sessions available), quantity, name (of the item) and used (sessions used)
crm_show_available_sessions($user_id): Option values for select list. Usage: <select name="item_id"><?php crm_show_available_sessions($user_id); ?></select>
auto_redirect_to_homepage_after_logout()

*/
add_action( 'wp_logout', 'auto_redirect_to_homepage_after_logout');
add_filter( 'wp_new_user_notification_email', 'akimbo_crm_new_user_notification_email', 10, 3 );
add_filter( 'wp_new_user_notification_email_admin', 'akimbo_crm_new_user_notification_email_admin', 10, 3 );
add_action('user_new_form', 'guest_user_order_management');
add_filter( 'login_redirect', 'crm_login_redirect', 10, 3 ); //replace Theme My Login redirect
add_action('user_register', 'akimbo_crm_auto_add_user_as_student');
add_action('woocommerce_created_customer', 'akimbo_crm_auto_add_user_as_student_woocommerce'); 


function guest_user_order_management(){
	echo "update orders";
}

function user_edit_profile_link($user_id){
	global $wpdb;
	$link = get_site_url()."/wp-admin/user-edit.php?user_id=".$user_id;
	return $link;
}

/**
 * Redirect non-admins to the homepage after logging into the site.
 *
 * @since 	1.0
 */
function crm_login_redirect( $redirect_to, $request, $user  ) {
	//wordpress defaults to sending users to admin dashboard
	//https://codex.wordpress.org/Plugin_API/Filter_Reference/login_redirect
	if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('subscriber', $user->roles)) {
            $redirect_to =  home_url("/account");
        } 
		if (in_array('customer', $user->roles)) {
            $redirect_to =  home_url("/account");
        } 
		if (in_array('author', $user->roles)) {
            $redirect_to =  admin_url("/account");
        } 
    }
    return $redirect_to;
}


function casual_return_available_sessions($user, $age = NULL){
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
}

/**
 *
 * Option values for dropdown list. Usage: <select name="item_id"><?php crm_show_available_sessions($user_id); ?></select>
 * 
 */
function crm_show_available_sessions($user_id){
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
}

function crm_select_available_user_orders($user_id){
	global $wpdb;
	?><select name="item_id"><?php
	$query = new WC_Order_Query( array('orderby' => 'date','order' => 'DESC','customer_id' => $user_id,) );
	$orders = $query->get_orders();
	foreach ( $orders as $order ) {
		$order_id = $order->ID;
		$crm_order = wc_get_order($order_id);
		$items = $crm_order->get_items();
		foreach ( $items as $item_id => $item_data ) {
			$product_id = $item_data ['product_id'];
			$casual = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id' AND meta_key = 'pa_sessions'"); 
			$enrolment = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id' AND meta_key = 'weeks'"); 
			if($casual){
				$sessions = $item_data['pa_sessions'];
				$qty = $item_data['qty'];
				$sessions_used = $item_data['sessions_used'];
				$total = $sessions*$qty;
				$remaining = ($sessions*$qty)-$sessions_used;
				if(!$item_data['pa_sessions']){//$current_pricing = false;
				} elseif($remaining < 1){//$current_pricing = false;
				} else {
					$current_products[] = $item_data['product_id'];
					if (!$item_data['sessions_used']){$sessions_used = 0;$add_meta=true;}else{$sessions_used = $item_data['sessions_used'];}
					//echo "<option value='" .$order_id. "'>";
					echo $item_data['name'].": ".$sessions_used."/".$remaining." sessions";
					?><option value="<?php echo $item_id;?>"><?php echo $item_data['name'].": ".$sessions_used."/".$total." sessions";?></option><?php
				}
			} elseif($enrolment){
				$weeks = $item_data['weeks'];
				$variation_id = $item_data['variation_id'];
				if(!$weeks){
					$weeks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation_id'");
					wc_update_order_item_meta($item_id, "weeks", $weeks);
				}
				$qty = $item_data['qty'];
				$weeks_used = $item_data['weeks_used'];
				$remaining = ($weeks*$qty)-$weeks_used;
				if($remaining >=1){
					echo "Unassigned classes: ".$item_data['name'].", ".$remaining." remaining";
					?><option value="<?php echo $item_id; ?>"><?php echo $item_data['name'].", ".$remaining." remaining";?></option><?php
				}
			}else{
				?><option value="0"> Order <?php echo $order_id; ?>: Not available for use</option><?php //testing purposes
			}
		}
	}	
	?></select><?php
}

function auto_redirect_to_homepage_after_logout(){
	$site = get_site_url();
  wp_redirect( $site );
  exit();
}

function akimbo_crm_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
    $message = sprintf(__('Hi %s,'), $user->display_name) . "\r\n\r\n";
	$message .= __('To help simplify the transition from Mindbody, we have created a new account for you in Akimbo CRM with the details from your previous account. If you have a moment, please log in and check your account details have been imported correctly. Next time you book a class or workshop at Circus Akimbo, you’ll need to use the new system.'). "\r\n\r\n";
    $message .= __('To login, please set your password from the link below, using this email address as your user name.')."\r\n\r\n";
	$message .= network_site_url("/wp-login.php?action=lostpassword") . "\r\n\r\n";
	$message .= __('If you have any questions please let me know!') . "\r\n\r\n";
	$message .= __('Cheers, Claire') . "\r\n\r\n";

    $wp_new_user_notification_email['message'] = $message;

    return $wp_new_user_notification_email;

}

function akimbo_crm_new_user_notification_email_admin($wp_new_user_notification_email, $user, $blogname) {

    $user_count = count_users();

    $wp_new_user_notification_email['subject'] = sprintf('[%s] New user %s registered.',$blogname, $user->user_login);
    $wp_new_user_notification_email['message'] =
    sprintf( "%s has registerd to your blog %s.", $user->user_login, $blogname) .
"\n\n\r" . sprintf("This is user number %d!", $user_count['total_users']);

    return $wp_new_user_notification_email;

}

function akimbo_crm_auto_add_user_as_student($user_id) {//automatically adds a new student when user registers
	global $wpdb;
	//if($_POST['role'] == 'customer'){} <--maybe add this later
	$table = $wpdb->prefix.'crm_students';
	$data = array(
		'user_id' => $user_id,
		'student_rel' => 'user',
		'student_firstname' => $_POST['first_name'],
		);
	$wpdb->insert($table, $data);
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