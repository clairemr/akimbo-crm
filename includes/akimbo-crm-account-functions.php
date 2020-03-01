<?php //Woocommerce Account Custom Functions


/**
 *
 * Akimbo CRM account functions and shortcuts
 * 
 */

/*************
Reference list
**************

*/
add_action('woocommerce_account_dashboard', 'crm_dashboard');
add_filter( 'woocommerce_account_menu_items', 'crm_account_menu_items', 10, 1 );
add_action( 'init', 'akimbo_crm_add_my_account_endpoint' );
add_action( 'woocommerce_account_students_endpoint', 'akimbo_crm_students_endpoint_content' );
add_action( 'woocommerce_account_badges_endpoint', 'akimbo_crm_badges_endpoint_content' );
 //User Order Page Functions
add_filter('woocommerce_order_item_display_meta_key', 'change_order_item_meta_title', 20, 3 );
add_filter('woocommerce_order_item_display_meta_key', 'change_weeks_used_title', 20, 3 );
add_action( 'woocommerce_before_account_orders', 'orders_endpoint_content' );
add_action('woocommerce_order_details_after_order_table', 'order_booking_details');


function crm_dashboard(){
	//include "crm_dashboard.php";
	global $wpdb;
	//$customer = wp_get_current_user();////$customer = get_userdata( $user_id );
	$user_id = get_current_user_id();
	$user = new Akimbo_Crm_User(get_current_user_id());
	$student_id = (isset($_GET['student'])) ? $_GET['student'] : $user->get_user_student_id();
	$student = new Akimbo_Crm_Student($student_id);
	$today = current_time('Y-m-d-h:ia');

	echo apply_filters('akimbo_crm_above_account_dashboard_message', "<p>".get_option('akimbo_crm_account_message')."<p><hr>");
	
	
	if($student->get_student_info()->student_waiver <= 0){echo "<p><b>Please <a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."/students/?student_id=".$student_id."'>update your student details</a> before attending your next class</b></p><hr>";}

	$age = $student->get_age();
	$age = (isset($age)) ? $age : $age = "adult";//ternary operator, defaults to adult	
	echo "<h2>Upcoming ".ucwords($age)." Classes</h2>";
	echo "<i>Student: ".$student->first_name()."</i><br/>";
	crm_bookable_timetable($age, 14, $user, $student);
	/*switch ($age) {
	    case "adult":  	
	    	crm_bookable_timetable($age, 14, $user, $student);
	    break;
	    case "playgroup": 
	    	echo "<h2>Upcoming ".ucwords($age)." Classes</h2>";
	    	crm_bookable_timetable($age, 14, $user, $student);
	    break;
	    case "kids":    	
		echo "<h2>Student enrolments</h2>";
		crm_bookable_timetable($age, 14, $user, $student);
	    break;
	}*/
	
	
	//crm_bookable_timetable('adult', 14, $user, $student);//test only
}

function crm_bookable_timetable($age = NULL, $limit = 14, $user = NULL, $student = NULL, $url = NULL){
	global $wpdb;
	$today = current_time('Y-m-d-h:ia');
	$student_classes = ($student != NULL) ? $student->get_upcoming_classes(): NULL;//array of class objects
	if($age == NULL){
		$class_list_ids = $wpdb->get_results("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$today' ORDER BY session_date ASC LIMIT $limit");
	}else{
		$class_list_ids = $wpdb->get_results("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$today' AND age_slug = '$age' ORDER BY session_date ASC LIMIT $limit");
	}
	
	$product_id = 0;
	$item_id = 0;
	if($user != NULL){
	//echo $age;
		$order = $user->get_user_subscriptions($age);
		if($order['order_id'] <= 0){$order = $user->available_orders($age);}
		if(isset($order)){
			if($order['type'] == "subscription"){echo "Membership active: ";}
			$remaining = (isset($order['sessions'])) ? $order['sessions'] - $order['sessions_used'] : $order['weeks'] - $order['weeks_used'];
			//var_dump($order);
			if($remaining >= 1){//only show for casual users, not enrolments
				echo "You have ".$remaining."/".$order['sessions']." ".$age." classes remaining, expiring on ".date(" l jS F", strtotime($order['expiry'])).". ".$order['url'];
			}
			$product_id = $order['product_id'];
			$item_id = ($order['sessions'] > $order['sessions_used']) ? $order['item_id'] : 0;
			$type = $order['type'];
		}
	}
	if($user != NULL && $order['order_id'] <= 0){
		echo "<br/><i>Please visit the <a href='".get_permalink( woocommerce_get_page_id( 'shop' ) )."'>store</a> to purchase a class pass</i>";
	}elseif($user == NULL && $order['order_id'] <= 0){
		echo "<br/><i>Please <a href='".wp_login_url( get_permalink() )."' title='Login'>log in</a> or visit the <a href='".get_permalink( woocommerce_get_page_id( 'shop' ) )."'>store</a> to purchase a class pass</i>";
	}
	
	echo "<table>";
	foreach($class_list_ids as $class_list_id){
		$class = new Akimbo_Crm_Class($class_list_id->list_id);
		$class_info = $class->get_class_info();
		echo "<tr><td>".$class_info->class_title."<br/><small>".date("g:ia l jS F Y", strtotime($class_info->session_date)).", ".$class_info->location."</small></td><td>";
		
		if(isset($student_classes)){
			foreach($student_classes as $upcoming_class){
				if($class->get_class_info()->list_id == $upcoming_class->list_id){$enrolled = true;}
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
					if($student != NULL && in_array($product_id, $product_ids) && $age != 'kids'){
						crm_casual_enrol_button($student->student_id, $class_list_id->list_id, $item_id, NULL, $class_info->age_slug);
					} else{
						echo apply_filters('akimbo_crm_account_buy_pass', "<a href='".get_permalink(reset($product_ids))."'><button>Buy Pass</button></a>");//defaults to first value of array
					}
				}
			}
		}
	    echo "</td></tr>";
	    unset($enrolled);
	}
	echo "</table>";
}

/*
*
* Add students & badges as menu items
*
*/
function crm_account_menu_items( $items ) {
	unset( $items[ 'customer-logout' ] );
    $items['students'] = __( 'Students', 'crm' );
	$items['badges'] = __( 'Badges', 'crm' );
	$items['customer-logout'] = __( 'Logout', 'woocommerce' );
 
    return $items;
}
 
/*
*
* Add students & badges endpoints
*
*/
function akimbo_crm_add_my_account_endpoint() {
    add_rewrite_endpoint( 'students', EP_PAGES );
	add_rewrite_endpoint( 'badges', EP_PAGES );
}
 
/*
**
 * Add students endpoint HTML content.
 */
 function akimbo_crm_students_endpoint_content() {
	global $wpdb;
	$customer = wp_get_current_user();
	$user_id = get_current_user_id();
	$path = get_permalink( get_option('woocommerce_myaccount_page_id') )."/students/";
    
	if(isset($_GET['message']) && !isset($_GET['student_id'])){
		echo "Success, student updated!<br/>";
	}
	
	if(isset($_GET['student_id'])){
		$student_id = $_GET['student_id'];
		if($student_id == "new" || $student_id <= 0){//should no longer be required
			echo "<h2>Add Student</h2>";
			//akimbo_crm_student_enrolment_form($user_id, "new", "/account/students/");
			update_student_details_form(NULL, "/account/students/");
		} else {
			$student = new Akimbo_Crm_Student($student_id);
			$info = $student->get_student_info();
			$url = "/wp-admin/admin.php?page=akimbo-crm&tab=details&student_id=".$student_id;
			if(isset($_GET['message'])){echo "Success, ".$info->student_firstname." has been updated!<br/><hr><br/>";}
			echo "<h2>".$info->student_firstname." ".$info->student_lastname."</h2>";
			if(isset($student->first_class()->session_date)){
				$mailchimp = $student->update_mailchimp();
				$name = $info->student_rel == "user" ? "Your" : $info->student_firstname."'s";
				echo "<br/>".$name." most recently enrolled class: ".date("g:ia, l jS F", strtotime($student->last_class()->session_date));
				echo "<br/>".$name." first class at Circus Akimbo: ".date("g:ia, l jS F Y", strtotime($student->first_class()->session_date));
				if($mailchimp != NULL){
					if($mailchimp->MCstart > 0000-00-00 && $mailchimp->MCstart < $student->first_class()->session_date){
						echo " <i>(or the first class in the new system anyway! The first class you booked was on ".date("l jS F Y", strtotime($mcstart)).")</i>";
					}
				}else{
					echo "Did you know you aren't subscribed to our newsletter? You might be missing important information about classes! Click <a href='http://eepurl.com/KAagf'>here</a> to update your subscription.";
				}
			}else{
				echo "Head to your <a href='".get_permalink( get_option('woocommerce_myaccount_page_id') )."'>account dashboard</a> to book your first class!";
			}
			echo "<hr>";
			$url = $path."?student_id=".$student_id;
			update_student_details_form($student_id, $url);		}
			echo '<br/><hr>';
	}/*else{
		echo "<h2>Add Student</h2>";
		update_student_details_form(0, "/account/students/");
	}*/
	
	echo '<h2>Students</h2>';
	$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id");
	if($students){
		echo "<b>The following students are registered to your account</b>";
		foreach ($students as $student){$st_id = $student->student_id;
			echo "<br/>Student name: ". $student->student_firstname." <a href='".$path."/?student_id=".$student->student_id."'>View student details</a>";
		} 
		$classes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE user_id = $user_id ");;
		echo "<br/><br/><p style='text-align:center'>You have booked ".$classes." classes at Circus Akimbo. Thanks for choosing us to play a part in your circus journey!</p>";
		echo "<hr>";
	} else {
		echo "<br/><hr>";
	}
	
	echo "<p style='text-align:center'><a href='".get_site_url()."/account/students/?student_id=new'><button>Add a new student</button></a></p>";
}

/*
**
 * Add Badges endpoint HTML content.
 */
 function akimbo_crm_badges_endpoint_content(){ ?>
	<style>
	.myProgress {
	  width: 100%;
	  background-color: #ddd;
	  text-align: center; 
	}

	.myBar {
	  height: 30px;
	  background-color: #33ccff;
	}

	.oval {
	  height: 60px;
	  width: 50px;
	  background-color: #aaaaaa;
	  border-radius: 40%;
	  float: left;
	  text-align: center; 
	}
	</style>


	<?php
	global $wpdb;
	$user_id = get_current_user_id();
	date_default_timezone_set('UTC');
	$today = date('l jS \of F Y');
	$site = get_site_url();

	if(isset($_GET['student'])){
		$student_id = $_GET['student'];
		$student = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_students WHERE student_id = $student_id");
	}else{
		$student = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id AND student_rel = 'user'");
		if(!$student){
			$student = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id LIMIT 1");//in case user isn't the rel, pick first student
		}
		$student_id = $student->student_id;
	}

	echo "Badges are launching soon! You'll be able to track your classes, challenge yourself and invite friends to join you. You can get ahead by inviting friends from the referral code at the bottom of your <a href='https://www.circusakimbo.com.au/account'>account dashboard</a> or check back soon to see what we're building.";

	$capability = 'upload_files';//staff
	if (current_user_can($capability)){
		
		echo "<br/><br/><h1><strong>Test site, for Akimble eyes only</strong></h1>";
		
		/*
		*
		* August Special Offer
		*
		*/

		$friends = badge_page_count_coupons($user_id);
		/*echo "<p align='center'>This month we're celebrating friends at Akimbo! Bring along a friend for a $10 trial to get $10 off your next order, plus an entry to win 10 free classes for anyone booking with your referral link. Full details are up at LINK.";
		echo "<br/>You have ".$friends['aug19']." entries</p>";*/


		echo "<h2>Badges: ".$student->student_firstname."</h2>"; 

		//Badge variables
		$values = crm_badge_attendance_values($user_id, $student_id);
		$student_classes = $values['student_classes'];
		$types = $values['types'];
		$privates = $values['privates'];
		$first = $values['first'];
		$week_record = $values['week_record'];
		//$ = $values[''];
		//$friends = badge_page_count_coupons($user_id);

		$progress = array
			(	
			//badge name - current badge quantity - current count - next milestone - max quantity
			array("Number Of Classes Attended", $student_classes, array(1, 5, 10, 25, 50, 100, 200, 500)),
			array("Invite A Friend", $friends['total'], array(1, 2, 5, 10, 25)),
			array("Types Of Classes", $types, array(2, 4, 7, 10)),
			array("Classes Attended In The Same Week", $week_record, array(1, 2, 3, 4, 5, 6, 7)),
			array("Take A Private Lesson", $privates, array(1, 5, 10, 25, 50)),
			
			//array("Attend Open Training", 1, array(1, 5, 10, 25, 50)),
			//array("Tag Akimbo On Facebook Or Instagram", 0, array(1, 3, 6, 10)),
			//boolean badge tiers
			//array("Upload A Profile Picture", 0, array(1)),
			//array("Perform With Akimbo", 1, array(1)),
		);
		  
		for ($i = 0; $i < sizeOf($progress); $i++) {  
			echo "<br>";
			echo crm_merp_selector($progress[$i], $i);
		}

		/**************
		Badges
		***************
		Number of classes: add progress bar towards next goal
		Classes in one week
		Different types of classes
		Open training
		Consistency badge: number of weeks in a row
		Invite a friend
		Share on fb
		Upload a picture
		Perform with Akimbo
		Take a private lesson

		//ideas: https://www.khanacademy.org/badges

		*/


		echo "<h2>Milestones: ".$student->student_firstname."</h2>"; 
		echo "Your first circus class: ".date("l jS M Y", strtotime($values['first']))."<br/>";
		echo "Your most recent circus class: ".date("l jS M Y", strtotime($values['last']))."<br/>";
		
		$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id");
		if($students){
			echo "<br/><b>See badges for a different student</b>";
			foreach ($students as $student){$st_id = $student->student_id;
				echo "<br/>Student name: ". $student->student_firstname." <a href='".get_site_url()."/account/badges/?student=".$student->student_id."'>View badges</a>";
			} 
		}else{
			echo "<br/>When you start attending classes you'll start earning badges!";
		}
		
		
	}
	
 }

/**
 *
 * Akimbo CRM badge page functions
 * 
 */

/*************
Reference list
**************

//Badge variables//
badge_page_count_coupons(): count referral coupons to calculate friends invited

//Badge functionality//
crm_merp_selector($set, $pos)
getNextMilestone($set)
numberOfBadges($set)

*/


/**
 * Badges page - get number of referral coupons
 * 
 * based on public/class-gens-raf-public.php
 *
 * @since    1.0.0
 */
function badge_page_count_coupons($user) {
	$user_info = get_userdata($user);
	$user_email = $user_info->user_email;
	$date_format = get_option( 'date_format' );
	$args = array(
		'posts_per_page'   => -1,
		'post_type'        => 'shop_coupon',
		'post_status'      => 'publish',
		'meta_query' => array (
			array (
			  'key' => 'customer_email',
			  'value' => $user_email,
			  'compare' => 'LIKE'
			)
		),
	);
		
	$coupons = get_posts( $args );

	if($coupons) { 
		$total = 0;
		foreach ( $coupons as $coupon ) {
			if(substr( $coupon->post_title, 0, 3 ) != "RAF") {//borrowed from plugin code
				continue;
			}
			$total++;
			//$post_date = current_time('Y-m-d-h:ia'); //test data
			$post_month = date("m-Y", strtotime($coupon->post_date));
			//$current_order['sessions'] = $item_data['pa_sessions'];
			//$current_order['order_id'] = $order_id;
			$aug19 = 0;
			if($post_month == "08-2019"){//if within August
				$aug19 ++;
			}
		}
	}else{
		$total = 0;
		$aug19 = 0;
	}
	$count['total'] = $total;
	$count['aug19'] = $aug19;
	
	return $count;
}

function crm_badge_attendance_values($user, $student){
	global $wpdb;
	$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance  
	LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id
	WHERE student_id = $student
	ORDER BY {$wpdb->prefix}crm_class_list.session_date ASC");
	$count = 0;
	$types = array();
	$privates = 0;
	$first = 1;
	if($classes){$week_record = 1;}
	foreach($classes as $class){
		if($first == 1){
			$first = $class->session_date;
			$previous = date("W-m-Y", strtotime($class->session_date));
			$weeks = 1;
		}
		if($class->attended == 1){$count++;}
		if(!in_array($class->class_title, $types)){$types[] = $class->class_title;}
		if($class->age_slug == "private"){$privates++;}
		//weeks record
		$week = date("W-m-Y", strtotime($class->session_date));
		echo "w".$week."p".$previous;
		if($week == $previous){$weeks++;}
		if($weeks >= $week_record){$week_record = $weeks;}
		if($week != $previous){$weeks=0;}
		$previous = date("W-m-Y", strtotime($class->session_date));
		
	}
	
	$values['user_classes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE user_id = $user ");
	$values['student_classes'] = $count;
	$values['types'] = sizeOf($types);
	$values['privates'] = $privates;
	$values['first'] = $first;
	$values['week_record'] = $week_record;
	$values['last'] = $previous;
	
	return $values;
}

function crm_merp_selector($set, $pos){
	$noOfMilestones = sizeOf($set[2]);
	$noOfBadges = numberOfBadges($set);
	$nextMilestone = getNextMilestone($set);
	?>
	<h1><?php echo $set[0]; ?></h1>
	<div class="myProgress">
	<div id="myBar<?php echo $pos; ?>" style="width:<?php echo $set[1]/$nextMilestone*100; ?>%" class="myBar"><?php echo $set[1]; ?></div>
	</div>
	0 
	<p id='jstest<?php echo $pos; ?>' style="float:right"><?php echo $nextMilestone; ?></p><br>
	<?php
	for ($i = 0; $i < $noOfMilestones; $i++) {
		if($i < $noOfBadges){
			echo "<div id='bn".$pos."-".$i."' class='oval' style='background-color: #33ccff'>".$set[2][$i]."</div>";}
		else{
			echo "<div id='bn".$pos."-".$i."' class='oval'>".$set[2][$i]."</div>";}
	}?><br><br><br>
	<!--<button onclick='move(<?php //echo json_encode($set); ?>, <?php //echo $pos; ?>)'>Click Me</button><br>-->
	<?php
 }
 
 function numberOfBadges($set){
	for ($i = 0; $i < sizeOf($set[2]); $i++) {
		if($set[1] < $set[2][$i]){
			return $i;
		}
	}
	return sizeOf($set[2]);
 }
 
 function getNextMilestone($set){
	for ($i = 0; $i < sizeOf($set[2]); $i++) {
		if($set[1] < $set[2][$i]){
			return $set[2][$i];
		}
	}
	return max($set[2]);
 }



//https://github.com/woocommerce/woocommerce/wiki/Customising-account-page-tabs
/**
 * Endpoint HTML content.
 */
function orders_endpoint_content() {
	echo '<p>Visit your account to view your schedule and enrol for classes, or click view order to full details, including how many sessions remain on that pass</p>';
}

//https://www.ibenic.com/manage-order-item-meta-woocommerce/
function change_order_item_meta_title( $key, $meta, $item ) {
    // By using $meta-key we are sure we have the correct one.
    if ( 'sessions_used' === $meta->key ) { $key = 'Sessions used'; }
    return $key;
}
function change_weeks_used_title( $key, $meta, $item ) {
    if ( 'weeks_used' === $meta->key ) { $key = 'Weeks enrolled'; }
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


/**
 *
 * Potentially outdated
 * 
 */

/*************
Reference list
**************


*/




