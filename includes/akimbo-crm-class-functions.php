<?php //Crm Class Functions

/***********************************************************************************************
 * 
 * Display Functions
 * 
 ***********************************************************************************************/

/*
* Admin area list of all classes
*/
function crm_class_list($start = NULL, $end = NULL, $length = 10, $format = "display"){
	global $wpdb;
	$start = ($start != NULL) ? $start : current_time('Y-m-d');
	if($end != NULL){
		$classes = $wpdb->get_col("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$start' AND session_date <= '$end' ORDER BY session_date ASC" );
	} else{
		$classes = $wpdb->get_col("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$start' ORDER BY session_date ASC LIMIT $length " );
	}
	if($classes  && $format == "display"){
		$display = "<table><tr><th width='55%'>Class</th><th width='20%'>Trainer/s</th><th colspan='2' width='25%'>Enrolments</th></tr>";
		foreach ( $classes as $class_id ) {
			$class = new Akimbo_Crm_Class($class_id);//->list_id
			$class_type = crm_check_class_type($class_id);
			$class_info = $class->get_class_info();
			$display .=  "<tr><td>".$class->get_date().": ".$class_info->class_title."</td><td>";
			$display .=  $class->trainer_names();
			if($class_type != "booking"){
				$capacity = $class->capacity();
				$display .=  "</td><td>".$capacity['count']."/".$capacity['capacity']."</td><td>".$class->class_admin_link("View Class")."</tr>";
			}else{
				$display .=  "</td><td colspan='2' align='center'>".$class->class_admin_link("View Booking")."</tr>";
			}
		}
		$display .= "</table>";
		echo $display;
	}elseif($format != "display"){
		return $classes;
	}else{
		echo "No classes found, please try a different date";
	}
}

/**
 * Manage Class page
 */
function akimbo_crm_manage_classes_details($class_id){
	$class = new Akimbo_Crm_Class($class_id);
	$class_info = $class->get_class_info();
	if(!$class_info){echo "No class found for that ID";
	}else{
		echo apply_filters('manage_classes_semester', "Semester: ". $class->class_semester());
		$class_type = $class->get_class_type();
		$student_info = $class->get_student_info();
		if(!$student_info){$student_info = array('student_ids' => 0, '$student_list' => 0);}
		do_action( 'akimbo_crm_manage_classes_before_attendance_table', $class_id );
		apply_filters('manage_classes_mark_attendance_table', display_attendance_table($class));
		do_action('manage_classes_enrolment', akimbo_crm_admin_manual_enrolment_button($class_id, $student_info['student_ids'], $class->get_class_type(), $class_info->age_slug)); 
		if($student_info['student_list']){//only show unenrol button if students are enrolled
			do_action('manage_classes_unenrolment', akimbo_crm_unenrol_student_form($student_info['student_list'], $class_id, akimbo_crm_class_permalink($class_id)));
		}
		if($class_type == "enrolment"){
			do_action('manage_classes_swap_student', akimbo_crm_swap_student_button($class, $student_info['student_list'])); 
			echo apply_filters('manage_classes_matched_orders', akimbo_crm_display_matched_orders($class));
		}
		$unpaid_students = $student_info['unpaid_students'];
		if(isset($unpaid_students)){
			echo apply_filters('manage_classes_attendance_table_title', "<h2>Unpaid Students</h2>");
			foreach($unpaid_students as $unpaid_student){
				echo $unpaid_student->full_name();
				akimbo_crm_update_unpaid_classes($unpaid_student->get_user_id(), $unpaid_student->att_id, $class->age_slug(), akimbo_crm_class_permalink($class_id));
			}				
		}

		//show other classes from the same term
		echo apply_filters('manage_classes_show_other_variations', display_related_classes($class));
		$class->email_list("echo");
		if(isset($user_list)){
			echo "<h2>Emails</h2>";
			crm_get_user_email_list($user_list);//custom functions
		}
		
		crm_update_class_date_form($class);
		echo "<br/><hr><br/>";		
	}
}

/**
 * Class Attendance table. Includes update trainers, mark attendance functions
 */
function display_attendance_table($class){
	$class_info = $class->get_class_info();
	echo "<br/><table width='80%' style='border-collapse: collapse;'><tr bgcolor = '#33ccff'><th colspan='3'><h2>";
	if($class->previous_class() >= 1){echo "<a href='".akimbo_crm_class_permalink($class->previous_class())."'><input type='submit' value='<'></a> ";}
	echo $class_info->class_title." ".date("g:ia, l jS M", strtotime($class_info->session_date));
	if($class->next_class() >= 1){echo  " <a href='".akimbo_crm_class_permalink($class->next_class())."'><input type='submit' value='>'></a>";	}
	echo "</h2></th></tr><tr><td colspan='3' align='center'>";
	crm_update_trainer_dropdown("class", $class->class_id, unserialize($class->get_class_info()->trainers));
	echo "</td></tr>";

	$class_student_info = $class->get_student_info();
	$students = $class_student_info['student_list'];
	if($students){
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
		$i=0;
		foreach($students as $student){
			$i++;//increment here so it gets correct number of students
			echo "<tr><td>".$student->student_id.". ".$student->student_admin_link($student->full_name())."</td><td>";
			if($student->ord_id >= 1){ 
				crm_order_info_from_item_id($student->ord_id, "url", $student->ord_id);
			}else{echo "UNPAID";}
			?></td><td><input type="checkbox" name="student_<?php echo $i?>" value="1" <?php if($student->attended >= 1){echo "checked='checked'";}?> 
			>Student <?php echo $i."</td></tr>";
			if($student->get_student_info()->student_notes){
				echo "<tr><td colspan='2'>Notes: ".$student->get_student_info()->student_notes."</td><td></td></tr>";
			}
			if($student->get_student_info()->student_waiver <= 0){
				echo "<tr><td colspan='2'>Has not completed waiver</td><td></td></tr>";
			}
			?><input type="hidden" name="student_<?php echo $i;?>_id" value="<?php echo $student->student_id;?>"><?php

		}
		?><input type="hidden" name="count" value="<?php echo $i;?>">
		<input type="hidden" name="class" value="<?php echo $class->class_id;?>">
		<input type="hidden" name="action" value="mark_attendance">
		<tr><td colspan='2'></td><td><input type='submit' value='Update Attendance'></td>
		</form><?php 
	}else{
		echo "<tr><td colspan='3'><br/>No students enrolled<br/><br/>";
		crm_simple_delete_button("crm_class_list", "list_id", $class->class_id, "/wp-admin/admin.php?page=akimbo-crm", "Delete class");
		crm_delete_class_series_button($class->class_id);
		echo "</td></tr>";
	}
	echo "</table><br/>";
}

/**
 * Display Matched Orders
 */
function akimbo_crm_display_matched_orders($class){
	$matched_orders = $class->matched_orders();
	if($matched_orders){
		echo "<table><tr><th colspan='2'>Matched Orders</th></tr>";
		foreach($matched_orders as $order){
			echo "<tr><td>".$order['title'].$order['details']."</td><td>";
			echo "Student: ";
			akimbo_crm_match_enrolment($user_id, $class_id, $item_id);
			echo "</td></tr>";
		}
		echo "</table>";
	}
}

/**
 * Display related classes
 */
function display_related_classes($class){
	$i=0;
	$class_variations = $class->enrolment_related_classes();
	$class_students = $class->get_student_info();
	echo "<table><tr><th colspan='6' align='center'>".$class->get_semester()."</th></tr><tr bgcolor = '#89ccff'><th>Week</th><th>Class Name</th><th>Class Date</th><th>Enrolled</th><th>Attended</th><th>Details</th></tr>";
	foreach($class_variations as $class){
		$i++;
		$class_info = $class->get_class_info();
		echo "<tr><td>".$i."</td><td>".$class_info->class_title."</td><td>";
		echo date("g:ia, l jS M", strtotime($class_info->session_date))."</td><td>";
		echo $class_students['count']."</td><td>".$class_students['attended']."</td><td>";
		echo akimbo_crm_class_permalink($class_info->list_id, "View")."</td></tr>";
	}
	echo "</table>";
}

/***********************************************************************************************
 * 
 * Class Actions
 * 
 ***********************************************************************************************/

add_action( 'admin_post_mark_attendance', 'mark_attendance' );

function mark_attendance(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	$count = $_POST['count'];
	for($i=0; $i<=$count; $i++){
		$value = "student_".$i;
		$id_value = "student_".$i."_id";
		$where = array('student_id' => $_POST[$id_value],'class_list_id' => $_POST['class'],);
		if(isset($_POST[$id_value])){
			$data = (isset($_POST[$value])) ? array('attended' => 1,) : array('attended' => 0,);
			$result = $wpdb->update( $table, $data, $where); 
		}
	}
	$url = akimbo_crm_class_permalink($_POST['class']);
	$message = ($result) ? "success" : "failure";
	$url .= "&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/**
* Update class details
*/
function crm_update_class_date_form($class){
	$class_info = $class->get_class_info();
	$date = explode(" ", $class_info->session_date);
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	Date: <input type="date" name="new_class_start" value="<?php echo $date[0]; ?>"><input type="time" name="new_class_time"  value="<?php echo $date[1]; ?>"> Length: <input type="number" name="duration" value="<?php echo $class_info->duration; ?>">
	<input type="hidden" name="id" value="<?php echo $class_info->list_id; ?>">
	<input type="hidden" name="action" value="update_class_date">
	<input type='submit' value='Update'>
	</form><?php
}

add_action( 'admin_post_update_class_date', 'crm_update_class_date_action' );

function crm_update_class_date_action(){
	global $wpdb;
	$new_date = $_POST['new_class_start']." ".$_POST['new_class_time'];
	$table = $wpdb->prefix.'crm_class_list';
	$where = array('list_id' => $_POST['id']);
	$data = array('session_date' => $new_date, 'duration' => $_POST['duration']);
	$result = $wpdb->update( $table, $data, $where);
	$message = ($result) ? "success" : "failure";
	$url = (isset($_POST['url'])) ? $_POST['url'] : akimbo_crm_class_permalink($_POST['id']);
	$url .= "&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/**
 * Delete Class Series button
 */
function crm_delete_class_series_button($class_id){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
	<input type='hidden' name='action' value='crm_delete_class_series'>
	<b><input type='submit' value='Delete Series'></b></form><?php
}

add_action( 'admin_post_crm_delete_class_series', 'crm_delete_class_series' );

function crm_delete_class_series(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_class_list';
	$class = new Akimbo_Crm_Class($_POST['class_id']);
	$series = $class->enrolment_related_classes('ids');
	foreach($series as $class_id){
		$class = new Akimbo_Crm_Class($class_id);
		$enrolments = $class->student_count();
		if($class->student_count() >=1){
			$update_id = $class_id;//redirect to class page to see who is enrolled
		}else{
			$wpdb->delete( $table, array( 'list_id' => $class_id,) );			
		}
	}	
	$url = (isset($update_id)) ? akimbo_crm_class_permalink($update_id)."&message=error" : akimbo_crm_permalinks("classes");
	wp_redirect( $url ); 
	exit;	
}

/***********************************************************************************************
 * 
 * Student functions for classes
 * 
 ***********************************************************************************************/

/**
* Update class id to swap to different class
*/
function akimbo_crm_swap_student_button($class, $student_list){//class object, array of student objects
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<select name= 'att_id'><option value="">Select student</option>
		<?php foreach ($student_list as $student){ 
			?><option value="<?php echo $student->att_id;?>"><?php echo $student->full_name();?></option><?php
		} ?></select> <select name='class_id'><?php 
		$classes = $class->related_classes();
		foreach ( $classes as $class ) {
			?><option value='<?php echo $class->list_id; ?>'><?php echo date("g:ia, l jS M", strtotime($class->session_date)).": ".$class->class_title; ?></option><?php 
		} ?></select><input type="hidden" name="action" value="crm_swap_student">
		<input type="submit" value="Swap student">
	</form><?php
}

add_action( 'admin_post_crm_swap_student', 'akimbo_crm_swap_student' );//passes new class_id & att_id

/**
 * Action for akimbo_crm_swap_student_button
 */
function akimbo_crm_swap_student(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	$data = array('class_list_id' => $_POST['class_id'],);
	$where = array('attendance_id' => $_POST['att_id']);
	$wpdb->update( $table, $data, $where); 
	wp_redirect( akimbo_crm_class_permalink($_POST['class_id']) ); 
	exit;	
}

/***********************************************************************************************
 * 
 * Enrolment and Unenrolment
 * 
 ***********************************************************************************************/
/**
 * Match Enrolment Orders
 */
function akimbo_crm_match_enrolment($user_id, $class_id, $item_id){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
	$exclude = array();//update to send student list
	echo crm_student_dropdown("student_id", $exclude, $user_id);
	?>
	<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
	<input type="hidden" name="item_id" value="<?php echo $item_id;?>">
	<input type="hidden" name="class_id" value="<?php echo $class_id;?>">
		Start Date: <input type="date" name="start_date">
	<input type="hidden" name="action" value="kids_enrolment_confirmation">
	<input type='submit' value='Enrol'> </form>
	<!--Add new student button-->
	<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
	<input type="hidden" name="class" value="<?php echo $this->class_id;?>">
	<input type="hidden" name="action" value="crm_add_student">
	or <input type="text" name="student" placeholder="Student first name"> <input type='submit' value='Add New'>
	</form><?php
}

add_action( 'admin_post_kids_enrolment_confirmation', 'kids_class_enrolment' );

function kids_class_enrolment(){//matched orders, where item_id is given	
	global $wpdb;
	
	//Get Student Info
	$student_id = $_POST['student_id'];
	$student = new Akimbo_Crm_Student($student_id);
	
	//Get Class info
	$class = new Akimbo_Crm_Class($_POST['class_id']);
	$class_info = $class->get_class_info();
	$attendance_array = $student->class_attendance();//array of attended class ids
	$classes = ($_POST['start_date'])? $class->enrolment_related_classes('all', $_POST['start_date']) : $class->enrolment_related_classes('all');
	
	//Get pass info
	$item_id = $_POST['item_id'];
	$item_info = crm_get_item_available_passes($item_id);
	$remaining = $item_info['remaining'];

	//Update Attendance table
	$table = $wpdb->prefix.'crm_attendance';
	foreach($classes as $enrol_class){
		$data = array(
		'class_list_id' => $enrol_class->class_id,
		'user_id' => $_POST['user_id'],
		'student_id' => $student_id,
		'student_name' => $student->full_name(),
		);
		/**
		 * Attendance check not currently working
		 */
		if(in_array($enrol_class->class_id, $attendance_array)){//student already enrolled, update order id if needed
			$att_order_id = $wpdb->get_var("SELECT ord_id FROM {$wpdb->prefix}crm_attendance WHERE student_id = '$student_id' AND class_list_id = '$class->class_id'"); 
			if($att_order_id <= 0 && $remaining >=1){
				$data['ord_id'] = $_POST['item_id'];
				$remaining = $remaining-1;
			}
			$where = array ('student_id' => $student->student_id, 'class_list_id' => $enrol_class->class_id);
			$wpdb->update( $table, $data, $where);
			$x++;
			
		}else{
			$data['ord_id'] = $_POST['item_id'];
			$remaining = $remaining-1;
			$result = $wpdb->insert($table, $data);
			$x++;
		}
		$finalclass = $enrol_class->class_id;
		$enddate = $enrol_class->get_class_info()->session_date;
		if($remaining <= 0){break;}
	}

	crm_calculate_passes_used($item_id);
	if($result){
		akimbo_crm_mailchimp_update_merge_field("ENDDATE", $enddate, $student->contact_email());//update Mailchimp most recent class
	}
	$message = ($result) ? "success" : "failure";
	$url = akimbo_crm_class_permalink($finalclass)."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/**
 * User dashboard class enrol button. Front end, only shown to users with active passes
 */
function crm_casual_enrol_button($student, $class, $order, $url = NULL, $age = NULL){//Front end
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="order" value="<?php echo $order; ?>">
	<input type="hidden" name="student" value="<?php echo $student; ?>">
	<input type="hidden" name="class" value="<?php echo $class; ?>">
	<input type="hidden" name="age" value="<?php echo $age; ?>">
	<?php // if($url != NULL){ <input type="hidden" name="url" value="<?php echo $url; "> <?php } ?>
	<input type="hidden" name="action" value="crm_enrol_student">
	<input type="submit" value="Sign Up"></form><?php 
}

function akimbo_crm_admin_manual_enrolment_button($class_id, $student_list, $class_type = "casual", $age = NULL){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<?php echo crm_student_dropdown("student", $student_list); ?>
	<input type="hidden" name="class" value="<?php echo $class_id;?>">
	<input type="hidden" name="type" value="<?php echo $class_type;?>">
	<input type="hidden" name="age" value="<?php echo $age;?>">
	<input type="hidden" name="url" value="<?php echo akimbo_crm_class_permalink($class_id);?>">
	<input type="hidden" name="action" value="crm_enrol_student"><?php
	echo "<input type='submit' value='Enrol'>";
	akimbo_crm_permalinks("students", "display", "Add new student", array("student" => "new", "class" => $class_id));
	echo "</form>";
}

add_action( 'admin_post_crm_enrol_student', 'akimbo_crm_enrol_student_process' );//set age to search for orders

/**
 * Process to enrol students. Updates attendance table, Mailchimp & item meta
 * Replaces update_casual_enrolment and akimbo_crm_admin_manual_enrolment
 * Update to use admin_assign_order_id
 * Update kids_class_enrolment to use the same function
 */
function akimbo_crm_enrol_student_process() {//values set on crm_dashboard.php, student, class & order id given
	global $wpdb;
	$student = $_POST['student'];
	$class = $_POST['class'];
	
	$student = new Akimbo_Crm_Student($_POST['student']);
	$student_name = $student->full_name();
	$student_user = $student->get_user_id();
	$age = (isset($_POST['age'])) ? $_POST['age'] : $student->get_age();
	
	$item_id = (isset($_POST['order'])) ? $_POST['order'] : 0;
	if($item_id <= 1 && $age != NULL){//require age to automatically search orders
		$user = new Akimbo_Crm_User($student_user);
		$current_order = $user->get_available_user_orders($age, true);//use true to get single order
		if(isset($current_order) && $current_order['item_id'] != NULL){
			$item_id = $current_order['item_id'];
		}		
	}

	$table = $wpdb->prefix.'crm_attendance';
	$data = array(
		'class_list_id' => $_POST['class'],
		'student_id' => $_POST['student'],
		'user_id' => $student_user,
		'student_name' => $student_name,
		'enrolled' => '1',
		'ord_id' => $item_id,
		'attended' => '0',
		);
	$result = $wpdb->insert($table, $data);
	
	//update order_itemmeta only if attendance update is successful
	$message = ($result) ? "success" : "failure";
	if( FALSE === $result ) {
	} else {
		if($item_id >= 1){//update item meta info
			$update = crm_calculate_passes_used($item_id);
		}
		
		//update Mailchimp most recent class// Switch over to do_action
		//e.g. do action mailchimp_update_recent_class($class_date, $user_id);
		$value = $wpdb->get_var("SELECT session_date FROM {$wpdb->prefix}crm_class_list WHERE list_id = $class ");
		$user_info = get_userdata($student_user);
		$email = $user_info->user_email;
		akimbo_crm_mailchimp_update_merge_field("ENDDATE", $value, $email);
	}

	$url = (isset($_POST['url'])) ? $_POST['url'] : get_permalink( wc_get_page_id( 'myaccount' ) );	
	$url .= "&message=".$message;
	wp_redirect( $url ); 
	exit;	
}


/**
 * Unenrol single student or choose from array of students.
 * Combines crm_student_unenrol_button & crm_admin_unenrolment_button
 */
function akimbo_crm_unenrol_student_form($att_id, $class_id = NULL, $url){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
		if(is_array($att_id)){
			echo "<select name= 'att_id'><option value=''>Select student</option>";
			foreach ($student_list as $student){ 
				echo "<option value='".$student->att_id."'>".$student->full_name()."</option>";
			} 
			echo "</select>";
		}else{
			echo "<input type='hidden' name='att_id' value='".$att_id."'>"; 
		}
		if(isset($class_id)){
			echo "<input type='hidden' name='class_id' value='".$class_id."'>"; 
		}
		?>
		<input type="hidden" name="action" value="unenrol_button">
		<input type="submit" name="submit" value="Unenrol">
	</form> <?php
}

add_action( 'admin_post_unenrol_button', 'crm_unenrolment_process' );

function crm_unenrolment_process(){//early cancel
	global $wpdb;
	//Disassociate pass
	if(!isset($item_id) || $item_id == 999999){//don't do anything else for unpaid orders	
	} else {
		admin_assign_order_id($item_id, 0, NULL, true);
	}

	//update CRM attendance
	$table = $wpdb->prefix.'crm_attendance';
	$result = $wpdb->delete( $table, array( 
		'attendance_id' => $_POST['att_id'],) 
	);

	//redirect url
	if(isset($_POST['url'])){
		$url = $_POST['url'];
	}elseif(isset($_POST['class_id'])){
		$url = akimbo_crm_class_permalink($_POST['class_id']);
	}else {
		$url = get_permalink( wc_get_page_id( 'myaccount' ) );
	}
	$message = ($result) ? "success" : "failure";
	$url .= "&message=".$message;
	wp_redirect( $url ); 
	exit;	
}