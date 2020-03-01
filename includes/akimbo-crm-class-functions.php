<?php
/**
 *
 * Crm Class Functions
 * 
 */

/*************
Reference list
**************
crm_display_attendance_table($class_id)//show markable attendance list of all students for a given class
	* Action: mark_attendance
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
akimbo_crm_swap_student_button($class_id, $student_info['student_list'])
//^Update to only show classes in the current semester
akimbo_crm_update_unpaid_student_orders
akimbo_crm_show_other_class_variations($variation_id)//show list of other classes with same variation id. Edit to only show current term

*/

add_action( 'admin_post_mark_attendance', 'mark_attendance' );
add_action( 'admin_post_add_new_schedule', 'add_new_schedule' );
add_action( 'admin_post_update_class_date', 'crm_update_class_date_action' );
add_action( 'admin_post_crm_add_new_semester', 'crm_add_new_semester' );

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
		apply_filters('manage_classes_mark_attendance_table', crm_display_attendance_table($class_id));
		if($student_info['count'] <= 0){apply_filters('manage_classes_cancel_class_button', crm_simple_delete_button("crm_class_list", "list_id", $class_id, "/wp-admin/admin.php?page=akimbo-crm2", "Delete class") );	}
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


		if(isset($unpaid_students)){
			echo apply_filters('manage_classes_attendance_table_title', "<h2>Update Order IDs</h2>");
			do_action('manage_classes_update_unpaid_students', akimbo_crm_update_unpaid_student_orders($class_id, $unpaid_students));		
		}

		//show other classes from the same term
		//echo apply_filters('manage_classes_show_other_variations', akimbo_crm_show_other_class_variations($variation_id));
		$class->email_list("echo");
		if(isset($user_list)){
			echo "<h2>Emails</h2>";
			crm_get_user_email_list($user_list);//custom functions
		}

		echo "<h2>Class Details</h2>";
		echo "<strong>Age: </strong>".$class->age_slug()."<br/><strong>Type: </strong>".$class->get_class_type()."<br/><strong>Semester: </strong>".$class->class_semester()."<br/>";
		crm_update_class_date_form("class", $class_id);
		echo "<br/><hr><br/>";
	}
}

function akimbo_crm_manage_schedules(){
	$capability = 'manage_options';
	if (current_user_can($capability)){
		if(isset($_GET['message'])){
			$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Schedule added!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
			echo apply_filters('manage_classes_schedule_update_notice', $message);}
		echo "<h2>Class Schedules</h2>
		Please <a href='".get_site_url()."/wp-admin/post-new.php?post_type=product'>add products</a> before using this page to add schedules";
		crm_add_new_class_schedule();
		echo "<h2>Semesters</h2>";
		display_semesters("future");
		echo apply_filters('manage_classes_add_semester_button', crm_add_new_semester_button());
		//https://stackoverflow.com/questions/47518280/create-programmatically-a-woocommerce-product-variation-with-new-attribute-value <-- add new variation		
	}else{echo "<br/>Sorry, you don't have permission to edit schedules";}
}
/**
 * End admin Area
 */


/*
*
* Display attendance table
*
*/
//add_filter( 'crm_display_attendance_table', 'crm_display_attendance_table', 10, 3 );
function crm_display_attendance_table($class_id){
	global $wpdb;
	$site = get_site_url();
	if(!$class_id){
		echo "<br/>Class not set";
	}else{
		$class = new Akimbo_Crm_Class($class_id);
		$class_info = $class->get_class_info();
		?><style>table td {
		    border-top: thin solid; 
		    border-bottom: thin solid;
		    border-collapse: collapse;
		}</style><?php
		echo "<br/><table width='80%' style='border-collapse: collapse;'><tr bgcolor = '#33ccff'><th colspan='3'><h2>";
		if($class->previous_class() >= 1){echo "<a href='".$class->class_admin_link($class->previous_class())."'><input type='submit' value='<'></a> ";}
		echo $class_info->class_title." ".date("g:ia, l jS M", strtotime($class_info->session_date));
		if($class->next_class() >= 1){echo  " <a href='".$class->class_admin_link($class->next_class())."'><input type='submit' value='>'></a>";	}
		echo "</h2></th>";
		$class_student_info = $class->get_student_info();
		$students = $class_student_info['student_list'];
		if($students){
			?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
			$i=0;
			foreach($students as $student){
				$i++;//increment here so it gets correct number of students
				echo "<tr><td>".$student->student_id.". ".$student->student_admin_link($student->full_name())."</td><td>";
				if($student->ord_id >= 1){ 
					echo "<a href='".crm_order_link_from_item_id($student->ord_id)."'>".$student->ord_id."</a>";}else{echo "UNPAID";}
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
			?>
			<input type="hidden" name="count" value="<?php echo $i;?>">
			<input type="hidden" name="class" value="<?php echo $class_id;?>">
			<input type="hidden" name="action" value="mark_attendance">
			<tr><td colspan='2'></td><td><input type='submit' value='Update Attendance'></td>
			</form><?php 
		}else{
			echo "<tr><td colspan='3'><br/>No students enrolled<br/><br/></td></tr>";
		}
		echo "</table><br/>";
	}
}

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

function akimbo_crm_admin_manual_enrolment_button($class_id, $student_list, $class_type = "casual", $age = NULL){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<select name="student"><?php crm_student_dropdown_values($student_list); ?></select>
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

//combined with admin_manual_unenrolment_unpaid_student_button
function crm_admin_unenrolment_button($class_id, $student_list, $class_type = "casual"){//array of student objects
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<select name= 'att_id'><option value="">Select student</option>
	<?php foreach ($student_list as $student){ 
		?><option value="<?php echo $student->att_id;?>"><?php echo $student->full_name(); ?></option><?php
	} ?>
	</select> 
	<input type="hidden" name="class_id" value="<?php echo $class_id;?>">
	<input type="hidden" name="url" value="/wp-admin/admin.php?page=akimbo-crm2&class=<?php echo $class_id; ?>"><?php 
	if($class_type == "casual"){
		?><input type="hidden" name="action" value="casual_unenrol_button"><?php //unenrolment process in student functions
	}else{
		?><input type="hidden" name="action" value="enrolment_unenrol_button"><?php //unenrolment process in student functions
	} ?>
	
	<input type="submit" value="Unenrol">
	</form><?php //<input type='submit' value='Late Cancel'> //Remove option - students are either given pass back or kept in class for record keeping purposes
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


/**
*
* Update class date, front end function
*
*/
function crm_update_class_date_form($type, $id){
	global $wpdb;
	$class = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = $id");
	//$class->class_title, $class->session_date
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	Date: <input type="date" name="new_class_start"><input type="time" name="new_class_time"> Length: <input type="number" name="duration" value="<?php echo $class->duration; ?>">
	<input type="hidden" name="id" value="<?php echo $id; ?>"><?php 
	if($type == "class"){
		?><input type="hidden" name="action" value="update_class_date"><?php
	}/*elseif($class == "booking"){
		?><input type="type" name="action" value="update_party_trainers"><?php
	}*/
	?><input type='submit' value='Update'>
	</form>
	    
	<?php
}
function crm_update_class_date_action(){
	global $wpdb;
	$new_date = $_POST['new_class_start']." ".$_POST['new_class_time'];
	$table = $wpdb->prefix.'crm_class_list';
	$where = array('list_id' => $_POST['id']);
	$data = array('session_date' => $new_date, 'duration' => $_POST['duration']);
	$wpdb->update( $table, $data, $where);
	
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm2&class_id=".$_POST['id'];
	wp_redirect( $url ); 
	exit;
}

/**
*
* Add new class schedule
*
*/
function crm_add_new_class_schedule(){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<br/>Select product: <select name= 'product_type'><?php
		$adult_ids = array(308);
		echo "<option value='adult'>Adults</option><option value='kids'>Youth Circus</option><option value='playgroup'>Playgroup</option><option value='training'>Open Training</option>";
		echo "<option>**********</option>";
		$posts = get_posts(array('post_type'=>'product', 'numberposts' => 100,'orderby'=> 'post_title','order' => 'ASC',
        //'category_name' => 'Classes' or cat ID 26, //<-- not working, try to only show classes so I can have less posts
        ));
		foreach($posts as $key=>$post){
		  $post_id = $post->ID;
		  //$category = get_the_category( $post->ID ); <-- get age slug from category
		  echo "<option value='".$post_id."'>".$post->post_title."</option>";
		}?> </select> 

	Semester: <select name= 'semester'><?php
	$semesters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_semesters ORDER BY semester_slug");
	foreach ($semesters as $semester){
		echo "<option value='".$semester->semester_end."'>".$semester->semester_slug."</option>";
	} ?> <option value="1">Single session</option></select>
	ID (if known): <input type="text" name="id" required>

	<br/>Select class name: <select name= 'class_name'><?php
		$classes = $wpdb->get_results("SELECT DISTINCT class_title FROM {$wpdb->prefix}crm_class_list ORDER BY class_title ASC");
		foreach ($classes as $class){
			echo "<option value='".$class->class_title."'>".$class->class_title."</option>";
		} ?> </select> or add new class: <input type="text" name="class_title">

	<br/>Class start date:<input type="date" name="new_class_start" required> Class time: <input type="time" name="new_class_time" required> Length: <input type="number" name="new_class_length" value="60">
	
	<br/>Trainer 1: <select name="trainer1"><option value="0">No trainer</option>
	<?php crm_trainer_dropdown_list(); ?></select>
	Trainer 2: <select name="trainer2"><option value="0">No trainer</option>
	<?php crm_trainer_dropdown_list(); ?></select>
	<input type="hidden" name="location" value="Circus Akimbo - Hornsby">
	<input type="hidden" name="action" value="add_new_schedule">
	<br/><input type="submit" value="Add schedule"></form><?php 
}

function add_new_schedule(){//values set on manage_classes.php
	global $wpdb;
	$table = $wpdb->prefix.'crm_class_list';
	$id = ($_POST['id']) ? $_POST['id'] : NULL;//ternary operator
	if(!isset($id)){
		$id = ($_POST['product_type'] == 'training') ? 0 : 1;
	}
	$class_title = ($_POST['class_title']) ? $_POST['class_title'] : $_POST['class_name'];//add new title or use existing name
	$semester_end = ($_POST['semester'] <= 2) ? $_POST['new_class_start'] : $_POST['semester'];//single or multiple sessions
	$new_date = $_POST['new_class_start']." ".$_POST['new_class_time'];
	if(!is_numeric($_POST['product_type'] )){//adult, kids or playgroup
		$option = "akimbo_crm_".$_POST['product_type']."_class_products";
		$age_slug = ($_POST['product_type'] == 'training') ? 'adult' : $_POST['product_type'];
		$product_type = get_option($option);
	}else{
		$age_slug = crm_product_get_age_slug($_POST['product_type']);
		$product_type = array($_POST['product_type']);
	}
	$trainers = array($_POST['trainer1'], $_POST['trainer2']);
	$semester = $wpdb->get_var("SELECT semester_slug FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$semester_end'");//doesn't add semester to single sessions
	//$semester = $wpdb->get_var("SELECT semester_slug FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$semester_end' AND semester_start <= '$semester_end' ORDER BY session_date DESC LIMIT 1");//show latest date
	while($new_date <= $semester_end){
		$data = array(
			'age_slug' => $age_slug,
			'prod_id' => serialize($product_type),//column format must be text, not int
			'class_id' => $id,
			'class_title' => $class_title,
			'location' => "Circus Akimbo - Hornsby",
			'session_date' => $new_date,
			'duration' => $_POST['new_class_length'],
			'trainers' => serialize($trainers),
			);
		if($semester  <= 2){$data['semester_slug'] = $semester;}
		$result = $wpdb->insert($table, $data);
		$new_date = date("Y-m-d-H:i", strtotime($new_date) + 604800);//add number of seconds in 7 days, g:ia time format 6:00pm
	}
	$message = ($result) ? "success" : "failure";
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=schedule&message=".$message;	
	wp_redirect( $url ); 
	exit;
	
}

/*
*
* show list of other classes with same variation id. Edit to only show current term
*
*/
function akimbo_crm_show_other_class_variations($variation_id){
	$variation_id = $class->class_id;
	if($variation_id >= 2){
		$i=0;
		echo "<table width='80%''><tr bgcolor = '#33ccff'><th>Week</th><th>Class Name</th><th>Class Date</th><th>Enrolled</th><th>Attended</th><th>Details</th></tr>";
		$class_variations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation_id' ");
		//update to order by date and show a limited number
		foreach($class_variations as $class_variation){
			$i++;
			echo "<tr><td>".$i."</td><td>".$class_variation->class_title."</td><td>".$class_variation->session_date."</td><td></td><td></td><td><a href='".$site."/wp-admin/admin.php?page=akimbo-crm2&class=".$class_variation->list_id."'>View</a></td></tr>";
		}
		echo "</table>";
	}
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
* Insert new line into semesters table
*
*/
function crm_add_new_semester_button(){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	Add new: <input type="text" name="semester_slug" placeholder="T4-2019"> Start: <input type="date" name="semester_start"> End: <input type="date" name="semester_end">
	<input type="hidden" name="action" value="crm_add_new_semester">
	<input type="submit" value="Add Semester"></form><?php 
}

function crm_add_new_semester(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_semesters';
	$data = array(
		'semester_slug' => $_POST['semester_slug'],
		'semester_start' => $_POST['semester_start'],
		'semester_end' => $_POST['semester_end'],
		);
	$wpdb->insert($table, $data);

	//akimbo_crm_redirect("classes");
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=schedule";
	wp_redirect( $url );
	exit;
}

function display_semesters($type = NULL, $format = "text"){
	global $wpdb;
	if($type == "future"){
		$today = current_time('Y-m-d');
		$semesters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$today' ORDER BY semester_start");
	}else{
		$semesters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_semesters ORDER BY semester_start");
	}
	
	foreach ($semesters as $semester){
		//$diff = strtotime($semester->semester_end, 0) - strtotime($semester->semester_start, 0);
		//$weeks = floor($diff / 604800);
		$weeks = weeks_between($semester->semester_start, $semester->semester_end);
		if($format == "text"){
			echo $semester->semester_slug.": ".date("D jS M, Y", strtotime($semester->semester_start))." - ".date("D jS M, Y", strtotime($semester->semester_end))." (".$weeks.")<br/>";
			//echo $semester->semester_slug.": ".date("D jS M, Y", strtotime($semester->semester_start))." - ".date("D jS M, Y", strtotime($semester->semester_end))." (".$weeks." weeks)<br/>";
		}elseif($format == "option"){
			?><option value="<?php echo $semester->semester_slug; ?> "><?php echo $semester->semester_slug." (".$weeks.")"; ?></option><?php 
		}
	}
}

function weeks_between($datefrom, $dateto){//accepted format = Y-m-d
	$difference        = (strtotime($dateto) - strtotime($datefrom)) + 86400; // Difference in seconds, plus one day to round up to a full week
	$days_difference  = $difference / 86400; //floor($difference / 86400);
        $weeks_difference = floor($days_difference / 7); //ceil($days_difference / 7); // Complete weeks, rounds up to nearest week. Use floor to round down
	$days_remainder   = floor($days_difference % 7);
	$weeks_difference .= ($days_remainder >= 1) ? " weeks, ".$days_remainder." days" : " weeks";

        return $weeks_difference;

    }
