<?php
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