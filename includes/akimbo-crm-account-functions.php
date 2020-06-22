<?php 
/**
 *
 * Akimbo CRM account functions and shortcuts
 * 
 */

/*********************************************************************************************************************************
*
* Account Dashboard
*
*********************************************************************************************************************************/
add_filter( 'woocommerce_account_menu_items', 'crm_account_menu_items', 10, 1 );
add_action( 'init', 'akimbo_crm_add_my_account_endpoint' );
add_action( 'woocommerce_account_students_endpoint', 'akimbo_crm_students_endpoint_content' );
add_action( 'woocommerce_account_badges_endpoint', 'akimbo_crm_badges_endpoint_content' );
add_action('woocommerce_account_dashboard', 'crm_dashboard');
add_shortcode('simpleTimetable', 'user_timetable_simple'); //[simpleTimetable age='adults']
add_shortcode('trialTimetable', 'user_timetable_trial'); //[trialTimetable age='adults']
add_shortcode('RAFreferralCode', 'akimbo_raf_referral_code'); //[RAFreferralCode]

/*
* Add students & badges as menu items
*/
function crm_account_menu_items( $items ) {
	unset( $items[ 'customer-logout' ] );
    $items['students'] = __( 'Students', 'crm' );
	$items['badges'] = __( 'Badges', 'crm' );
	$items['customer-logout'] = __( 'Logout', 'woocommerce' );
 
    return $items;
}
 
/*
* Add students & badges endpoints
*/
function akimbo_crm_add_my_account_endpoint() {
    add_rewrite_endpoint( 'students', EP_PAGES );
	add_rewrite_endpoint( 'badges', EP_PAGES );
}

/*
* Dashboard display
*/
function crm_dashboard(){
	global $wpdb;
	$user_id = get_current_user_id();
	$user = new Akimbo_Crm_User(get_current_user_id());
	$student_id = (isset($_GET['student_id'])) ? $_GET['student_id'] : $user->get_user_student_id();
	$student = new Akimbo_Crm_Student($student_id);
	$age = $student->get_age();
	$age = (isset($age)) ? $age : $age = "adult";//ternary operator, defaults to adult	

	echo apply_filters('akimbo_crm_above_account_dashboard_message', "<p>".get_option('akimbo_crm_account_message')."<p><hr>");
	
	if($student->get_student_info()->student_waiver <= 0){echo "<p><b>Please <a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."/students/?student_id=".$student_id."'>update your student details</a> before attending your next class</b></p><hr>";}
	
	echo "<h2>Upcoming ".ucwords($age)." Classes</h2>";
	echo "<i>Student: ".$student->first_name()."</i><br/>";
	$user->display_user_orders_account($age);
	crm_bookable_timetable($age, 14, $user, $student);
}

/*
* Bookable timetable, also used on timetable page
*/
function crm_bookable_timetable($age = NULL, $limit = 14, $user = NULL, $student = NULL, $trial = false){
	global $wpdb;
	$today = current_time('Y-m-d-h:ia');
	$student_classes = ($student != NULL) ? $student->get_upcoming_classes(): NULL;//array of class objects
	$order = ($user != NULL) ? $user->get_available_user_orders($age, true) : NULL;
	
	if($age == NULL){
		$class_list_ids = $wpdb->get_results("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$today' ORDER BY session_date ASC LIMIT $limit");
	}else{
		$class_list_ids = $wpdb->get_results("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$today' AND age_slug = '$age' ORDER BY session_date ASC LIMIT $limit");
	}
	if(empty($class_list_ids)){
		echo "<p align='center'><h2>No upcoming classes have been scheduled!</h2> Please contact us for more information about ".$age." classes</p>";
	}else{
		$header = "<table><tr><th>Class Name</th>";
		$header .= ($age == NULL) ? "<th>Age</th>" : "";//add age column if needed
		$header .= "<th>Places</th><th></th></tr>";
		echo $header;
		foreach($class_list_ids as $class_list_id){
			$class = new Akimbo_Crm_Class($class_list_id->list_id);
			$class_info = $class->get_class_info();
			echo "<tr><td>";
			if($class_info->class_id == 2){echo "Virtual Class: ";}
			echo $class_info->class_title."<br/><small>".date("g:ia l jS F Y", strtotime($class_info->session_date)).", ".$class_info->location."</small></td><td>";
			if($age == NULL){echo ucwords($class_info->age_slug)."</td><td>";}//fill age column if needed
			if($trial == true){//update at some point to take trial info from product metadata
				$places = $class_info->capacity - $class->student_count();
						echo "<i>".$places."/".$class_info->capacity." places available</i></td><td>";
				$product_cat = $class_info->age_slug;
				if($product_cat == "adult"){
					$content = "<a href='".get_permalink(1499)."'><button>Book Trial</button></a></td></tr>";
				}elseif($product_cat == "playgroup"){
					$content = "<a href='".get_permalink(1536)."'><button>Book Trial</button></a></td></tr>";
				}else{//default to kids trial
					$content = "<a href='".get_permalink(2046)."'><button>Book Trial</button></a></td></tr>";
				}

				echo $content;
			}else{
				$enrolled = false;
				if(isset($student_classes)){
					foreach($student_classes as $upcoming_class){
						if($enrolled == false){//only change if no match found
							$enrolled = ($class->get_class_info()->list_id == $upcoming_class->list_id) ? true : false;
						}
					}
				}
				if($enrolled == true){
					echo apply_filters('akimbo_crm_account_enrol_message', "<b> Enrolled</b></td><td>");
					if($age != 'kids' && $today <= $class_info->cancel_date) {
						apply_filters('akimbo_crm_account_session_unenrol', crm_student_unenrol_button($upcoming_class->attendance_id));
					}
				}else{
					$class_student_info = $class->get_student_info();
					if($class_info->class_id < 1 ){//Open training
						echo "<i>".$class_student_info['count']." bookings. This session is free with a valid pass!</i></td><td>";
						if(isset($order)){crm_casual_enrol_button($student->student_id, $class_list_id->list_id, 999999, NULL, $class_info->age_slug);}//eventually change to 'if order is a membership'
					}else{
						$places = $class_info->capacity - $class_student_info['count'];
						echo "<i>".$places."/".$class_info->capacity." places available</i></td><td>";
						if($class_student_info['count'] >= $class_info->capacity){
							echo apply_filters('akimbo_crm_account_capacity_reached', "<i>Class Full</i>");
						}else{
							$product_ids = unserialize($class_info->prod_id);
							if($order['remaining'] >= 1 && in_array($order['product_id'], $product_ids) && $age != 'kids'){
								crm_casual_enrol_button($student->student_id, $class_list_id->list_id, $order['item_id'], NULL, $class_info->age_slug);
							} else{
								echo apply_filters('akimbo_crm_account_buy_pass', "<a href='".get_permalink(reset($product_ids))."'><button>Buy Pass</button></a>");//defaults to first value of array
							}
						}
					}
				}
			    echo "</td></tr>";
			    $enrolled = false;
			}
			
		}
		echo "</table>";
	}
}

function user_timetable_simple($atts){	
	global $wpdb;
	global $post;
	extract(shortcode_atts(array('age' => '', 'length' => '', 'date' => ''), $atts));
	$content = NULL;
	$user_id = (is_user_logged_in()) ? get_current_user_id() : 0;
	if($user_id >= 1){
		$user = new Akimbo_Crm_User($user_id);
		$content .= $user->display_user_orders_account($age);
	}else{
		$user = NULL;
		$content .= "<br/><i>Please <a href='".wp_login_url( get_permalink() )."' title='Login'>log in</a> or visit the <a href='".get_permalink( wc_get_page_id( 'shop' ) )."'>store</a> to purchase a class pass</i>";
	}
	$student_id = ($user != NULL) ? $user->get_user_student_id() : NULL;
	if($student_id != NULL){
		$student = new Akimbo_Crm_Student($student_id);
		$content .= "<i>Student: ".$student->first_name()."</i><br/>";
	}else{$student = NULL;}
	if(!isset($age)){
		$age = (isset($student)) ? $student->get_age() : NULL;
	}
	if(!$length){$length="14";}

	$content .= crm_bookable_timetable($age, $length, $user, $student);
	
	return $content;
}

function user_timetable_trial($atts){	
	$today = current_time('Y-m-d g:ia');
	global $wpdb;
	global $post;
	
	extract(shortcode_atts(array('age' => '', 'length' => ''), $atts));
	
	if(!$age){$age="all";}
	if(!$length){$length="18";}
	$content .= crm_bookable_timetable($age, $length, NULL, NULL, "trial");

	return $content;
}

function akimbo_raf_referral_code($atts){//$page = null
	//based on plugins/refer-a-friend-for-woocommerce-by-wpgens/public/class-gens-raf-public.php 
	extract(shortcode_atts(array('page' => ''), $atts));
	
	if(!$page){$url = get_site_url();}else{
		$url = get_site_url().$page;
	}
	$user_id = get_current_user_id();
	if ( !$user_id ) {
	} else {
		$referral_id = get_user_meta($user_id, "gens_referral_id", true);
	}
	$refLink = esc_url(add_query_arg( 'raf', $referral_id, $url )); 
	$content = "<a href='".$refLink."'>".$refLink."</a>";

	return $content;
}
 
/********************************************************************************************************************************
*
* Add students endpoint HTML content.
*
*********************************************************************************************************************************/
 function akimbo_crm_students_endpoint_content() {
	global $wpdb;
	$user_id = get_current_user_id();
	$user = new Akimbo_Crm_User(get_current_user_id());
	if(isset($_GET['message']) && !isset($_GET['student_id'])){echo "Success, student updated!<br/>";}
	
	if(isset($_GET['student_id'])){
		$student_id = $_GET['student_id'];
		if(!is_numeric($student_id)){//=new
			echo "<h2>Add Student</h2>";
			update_student_details_form(NULL, "/account/students/");
		} else {
			$student = new Akimbo_Crm_Student($student_id);
			if(isset($_GET['message'])){echo "Success, ".$student->first_name()." has been updated!<br/><hr><br/>";}
			echo "<h2>".$student->full_name()."</h2>";
			$student->display_mailchimp();
			echo "<hr>";
			update_student_details_form($student_id, $student->student_account_link(NULL));		
		}
			echo '<br/><hr>';
	}
	
	echo '<h2>Students</h2>';
	$students = $user->get_student_ids();
	if($students){
		echo "<h4><b>The following students are registered to your account</b></h4>";
		foreach ($students as $student_id){
			$student = new Akimbo_Crm_Student($student_id);
			echo $student->student_list_display();
		} 
		$classes = $user->class_count();
		if($classes >= 2){echo "<br/><br/><p style='text-align:center'>You have booked ".$classes." classes at Circus Akimbo. Thanks for choosing us to play a part in your circus journey!</p>";}
	} 
	echo "<hr><p style='text-align:center'><a href='".get_site_url()."/account/students/?student_id=new'><button>Add a new student</button></a></p>";
}

/********************************************************************************************************************************
*
* Orders Endpoint HTML content.
*
*********************************************************************************************************************************/
add_action( 'woocommerce_before_account_orders', 'orders_endpoint_content' );
add_filter('woocommerce_order_item_display_meta_key', 'crm_change_order_item_meta_title', 20, 3 );
add_action('woocommerce_order_details_after_order_table', 'order_booking_details');

function orders_endpoint_content() {
	echo '<p>Visit your account to view your schedule and enrol for classes, or click view order to full details, including how many sessions remain on that pass</p>';
}

function crm_change_order_item_meta_title( $key, $meta, $item ) {
    // By using $meta-key we are sure we have the correct one.
    if ( 'sessions_used' === $meta->key ) { $key = 'Sessions used'; }
    if ( 'weeks_used' === $meta->key ) { $key = 'Weeks enrolled'; }
    if ( 'expiry_date' === $meta->key ) { $key = 'Expiry'; }
    return $key;
}

function order_booking_details(){
	echo "<h2>Classes</h2>";
	global $wp;
	$url = home_url( $wp->request );
	$order_id = basename($url);
	global $woocommerce;
	global $wpdb;
	$order = wc_get_order($order_id) ;
	$customer = wp_get_current_user();
	$items = $order->get_items();
	foreach ( $items as $item_id => $item_data ) {
		//echo $item_data['name'];
		//echo "Sessions used: ".$item_data['sessions_used'];//$item_data['quantity'],$item_id; var_dump($item_data);
		//var_dump($item_data);
		if($item_data['pa_sessions'] || $item_data['sessions']){ //adult classes currently storing item id instead of order id in attendance table
			$bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE ord_id = $item_id");
			//var_dump( $bookings);
			foreach ( $bookings as $booking) {
				$class_date = date("ga l jS M", strtotime($booking->session_date));
				echo $booking->class_title.", ".$class_date."<br/>";
			}
		} else {
			$bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id WHERE ord_id = $order_id");
			foreach ( $bookings as $booking) {
				$class_date = date("ga l jS M", strtotime($booking->session_date));
				echo $booking->class_title.", ".$class_date.": ".$booking->student_name."<br/>";
			}
		}
	} 
	echo "<hr>";
}