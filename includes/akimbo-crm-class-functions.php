<?php
/**
 *
 * Crm Class Functions
 * 
 */

/*************
Reference list
**************
akimbo_crm_admin_manual_enrolment_button($class_id, $student_list, $class_type = "casual")//array of student objects
	* Action - Casual: update_casual_enrolment //student functions
	* Action - Other: admin_manual_enrolment //student functions
*****Eventually Update enrolment function to add order id if available
crm_admin_unenrolment_button($class_id, $student_list, $class_type = "casual"){//array of student objects
	* Action - Casual: casual_unenrol_button //student functions
	* Action - Other: enrolment_unenrol_button //student functions
akimbo_crm_swap_student_button($class, $student_list){//class object, array of student objects
	* Action: akimbo_crm_swap_student//student functions
crm_update_class_date_form($type, $id)//$type = class
	* Action: update_class_date
crm_add_new_class_schedule()
	* Action: add_new_schedule
crm_add_new_semester_button()
	* Action: crm_add_new_semester
display_semesters($type = NULL)//$type = future

***Working, but not OOP:***
crm_class_list($start = NULL, $end = NULL)//List of classes, replaces crm_display_timetable in 2.0

***Not yet updated:***
akimbo_crm_show_matched_orders($variation_id): show matched orders for that class
akimbo_crm_swap_student_button($class_id, $student_info['student_list'])//Update to only show classes in the current semester
akimbo_crm_update_unpaid_student_orders
*/











/**
 *
 * Admin Area
 * 
 */
function akimbo_crm_manage_classes(){
	if(isset($_GET['message'])){
		$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
		echo apply_filters('manage_classes_update_notice', $message);
	}
	if(isset($_GET['class'])){
		akimbo_crm_manage_classes_details($_GET['class']);
		crm_class_list();//show next 10 classes
	} else{
		$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');
		$crm_date = crm_date_setter_week($date);
		echo "<h4>Week Starting: ".date("D jS M, Y", strtotime($crm_date['start']))."</h4>";
		crm_class_list($crm_date['start'], $crm_date['end']);
	}

	echo "<br/><hr><br/>";
	apply_filters('akimbo_crm_manage_classes_date_selector', crm_date_selector("akimbo-crm2", "classes"));
}

function akimbo_crm_manage_classes_details($class_id){
	$class = new Akimbo_Crm_Class($class_id);
	//echo "Semester: ". $class->class_semester();
	$class_info = $class->get_class_info();
	if(!$class_info){echo "No class found for that ID";
	}else{
		$class_type = $class->get_class_type();
		
		$student_info = $class->get_student_info();
		if(!$student_info){$student_info = array('student_ids' => 0, '$student_list' => 0);}//testing to see if its empty classes that aren't working
		do_action( 'akimbo_crm_manage_classes_before_attendance_table', $class_id );
		echo apply_filters('manage_classes_attendance_table_title', "<h2>Mark Attendance</h2>");
		apply_filters('manage_classes_update_trainer_dropdown', crm_update_trainer_dropdown("class", $class_id, unserialize($class->get_class_info()->trainers)));
		apply_filters('manage_classes_mark_attendance_table', $class->display_attendance_table());
		do_action('manage_classes_enrolment', akimbo_crm_admin_manual_enrolment_button($class_id, $student_info['student_ids'], $class->get_class_type(), $class_info->age_slug)); 
		//echo $class_type;
		if($student_info['student_list']){//only show unenrol button if students are enrolled
			do_action('manage_classes_unenrolment', crm_admin_unenrolment_button($class_id, $student_info['student_list'], $class_type));
		}
		if($class_type == "enrolment"){
			do_action('manage_classes_swap_student', akimbo_crm_swap_student_button($class, $student_info['student_list'])); 
			echo "<br/>";
			echo $class->matched_orders();
		}

		$unpaid_students = $student_info['unpaid_students'];
		if(isset($unpaid_students)){
			echo apply_filters('manage_classes_attendance_table_title', "<h2>Update Order IDs</h2>");
			foreach($unpaid_students as $unpaid_student){
				echo $unpaid_student->full_name();
				$url = "/wp-admin/admin.php?page=akimbo-crm2&class=".$class_id;
				$unpaid_student->update_unpaid_classes($unpaid_student->att_id, $class_id, $url);
			}				
		}

		//show other classes from the same term
		echo apply_filters('manage_classes_show_other_variations', $class->display_related_classes());
		$class->email_list("echo");
		if(isset($user_list)){
			echo "<h2>Emails</h2>";
			crm_get_user_email_list($user_list);//custom functions
		}

		echo "<h2>Class Details</h2>";
		echo "<strong>Age: </strong>".$class->age_slug()."<br/><strong>Type: </strong>".$class->get_class_type()."<br/><strong>Semester: </strong>".$class->class_semester()."<br/>";
		$class->crm_update_class_date_form();
		echo "<br/><hr><br/>";		
	}
}



function akimbo_crm_admin_manual_enrolment_button($class_id, $student_list, $class_type = "casual", $age = NULL){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<?php crm_student_dropdown("student", $student_list); ?>
	<input type="hidden" name="class" value="<?php echo $class_id;?>">
	<input type="hidden" name="type" value="<?php echo $class_type;?>">
	<input type="hidden" name="age" value="<?php echo $age;?>">
	<input type="hidden" name="url" value="/wp-admin/admin.php?page=akimbo-crm2&class=<?php echo $class_id;?>"><?php
	if($class_type == "casual"){
		?><input type="hidden" name="action" value="update_casual_enrolment"><input type="hidden" name="order" value="0"><?php //student functions
	}else{
		?><input type="hidden" name="action" value="admin_manual_enrolment"><?php //student functions
	}
	?><input type='submit' value='Enrol'> <a href='<?php echo get_site_url();?>/wp-admin/admin.php?page=akimbo-crm&tab=details&student=new&class=<?php echo $class_id;?>'>Add new student</a> </form><?php
}



/**
*
* Update class id to swap to different class
*
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





/*
*
* Replaces crm_display_timetable in 2.0
*
*/
function crm_class_list($start = NULL, $end = NULL, $length = 10, $format = "display"){
	global $wpdb;
	$start = ($start != NULL) ? $start : current_time('Y-m-d');//ternary operator
	if($end != NULL){
		$classes = $wpdb->get_col("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$start' AND session_date <= '$end' ORDER BY session_date ASC" );
	} else{
		$classes = $wpdb->get_col("SELECT list_id FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$start' ORDER BY session_date ASC LIMIT $length " );
	}
	if($classes  && $format == "display"){
		echo "<table><tr bgcolor = '#33ccff'><th>Class</th><th>Trainer/s</th><th>Enrolments</th><th>Attendance</th></tr>";
		foreach ( $classes as $class_id ) {
			$class = new Akimbo_Crm_Class($class_id);//->list_id
			$class_info = $class->get_class_info();
			echo "<tr><td>".$class->get_booking_date().": ".$class_info->class_title."</td><td>";
			echo $class->trainer_names()."</td><td>";
			$capacity = $class->capacity();//enrolments
			echo $capacity['count']."/".$capacity['capacity']."</td><td><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$class_id."'><i>View Class<i></a></tr>";
		}
		echo "</table>";
	}elseif($format != "display"){
		return $classes;
	}else{echo "No classes found, please try a different date";}
}

/*
*
* Actions
*
*/

add_action( 'admin_post_mark_attendance', 'mark_attendance' );
add_action( 'admin_post_update_class_date', 'crm_update_class_date_action' );
add_action( 'admin_post_crm_delete_class_series', 'crm_delete_class_series' );

function mark_attendance(){//values set on manage_classes.php
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	$count = $_POST['count'];
	for($i=0; $i<=$count; $i++){
		$value = "student_".$i;
		$id_value = "student_".$i."_id";
		$where = array('student_id' => $_POST[$id_value],'class_list_id' => $_POST['class'],);
		if(isset($_POST[$id_value])){
			$data = (isset($_POST[$value])) ? array('attended' => 1,) : array('attended' => 0,);
			$wpdb->update( $table, $data, $where); 
		}
	}	
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$_POST['class']."&message=success";
	wp_redirect( $url ); 
	exit;
}

function crm_update_class_date_action(){
	global $wpdb;
	$new_date = $_POST['new_class_start']." ".$_POST['new_class_time'];
	$table = $wpdb->prefix.'crm_class_list';
	$where = array('list_id' => $_POST['id']);
	$data = array('session_date' => $new_date, 'duration' => $_POST['duration']);
	$wpdb->update( $table, $data, $where);
	
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm2&class=".$_POST['id'];
	wp_redirect( $url ); 
	exit;
}

function crm_delete_class_series(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_class_list';
	//$id = $_POST['class_id'];
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
	$url = akimbo_crm_permalinks("classes");
	if(isset($update_id)){$url .= "&class=".$update_id."&message=error";}
	wp_redirect( $url ); 
	exit;	
}