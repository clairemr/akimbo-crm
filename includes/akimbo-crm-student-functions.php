<?php 
/**
 *
 * Crm Student Functions
 * 
 */

/*************
Reference list
**************
crm_casual_enrol_button($student, $class, $order)
function update_casual_enrolment()
session_unenrol_button($att_id)
function crm_casual_unenrolment(){//early & late cancel
crm_get_student_email_list($student_list, $format){//user emails from student ids. Could also return user data


*/
add_action( 'admin_post_crm_add_student', 'crm_add_student' );
add_action( 'admin_post_nopriv_crm_add_student', 'crm_add_student' );


add_action( 'admin_post_update_casual_enrolment', 'update_casual_enrolment' );
add_action( 'admin_post_admin_manual_enrolment', 'akimbo_crm_admin_manual_enrolment' );
add_action( 'admin_post_kids_enrolment_confirmation', 'kids_class_enrolment' );

add_action( 'admin_post_unenrol_button', 'crm_unenrolment_process' );

add_action( 'admin_post_crm_swap_student', 'akimbo_crm_swap_student' );//passes new class_id & att_id
//swap variation id <-- might be an order function

add_action( 'admin_post_admin_add_new_student', 'crm_admin_add_new_student' );
//possibly outdated function? Moved from enrolment functions
add_action( 'admin_post_admin_manual_unpaid_unenrolment', 'admin_manual_unenrolment_unpaid_student' );

add_action( 'admin_post_admin_assign_order_id', 'admin_assign_order_id' );

add_action( 'admin_post_crm_merge_duplicate_students', 'crm_merge_duplicate_students' );

function crm_find_duplicate_students($url = NULL){
	//Future update: also find students where user is the same and first name is the same
	global $wpdb;
	echo "<h2>Duplicate Students:</h2>";
	echo "<details>";
	$url = ($url == NULL) ? akimbo_crm_permalinks("students") : $url;
	$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students ORDER BY user_id");
	$distinct_students = array();
	$user_students = array();
	$user_id = 0;
	$duplicates = false;
	foreach($students as $student){
		$student_user = $student->user_id;
		$key = $student->student_firstname." ".$student->student_lastname;
		if (isset($distinct_students[$key]) || isset($user_students[$student->student_firstname]) && $student->user_id == $user_id) { // && $arr['key'] == 'value'
			$duplicates = true;
			$comparison_id = (isset($distinct_students[$key])) ? $distinct_students[$key] : $user_students[$student->student_firstname];
			echo "<table><tr><th>Student ".$comparison_id."</th><th>Student ".$student->student_id."</th></tr>";
			echo "<tr><td>";
			update_student_details_form($comparison_id, $url, 1);
			echo "</td><td>";
		    update_student_details_form($student->student_id, $url, 1);
		    echo "</td></tr><tr><td>OR ";
		    crm_merge_duplicate_students_button($student->student_id, $comparison_id);
		    echo "</td><td>OR ";
		    crm_merge_duplicate_students_button($comparison_id, $student->student_id);
		    echo "</td></tr></table><br/><hr><br/>";
		    $student1 = new Akimbo_Crm_Student($student->student_id);
			$student2 = new Akimbo_Crm_Student($comparison_id);
		}else{
			$distinct_students[$key] = $student->student_id;
			$user_students[$student->student_firstname] = $student->student_id;
		}
		$user_id = $student->user_id;
	}
	if($duplicates == false){echo "No duplicates found";}
	echo "</details>";
}

function crm_merge_duplicate_students_button($student1, $student2){//discard $student2
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="student1" value="<?php echo $student1; ?>">
	<input type="hidden" name="student2" value="<?php echo $student2; ?>">
	<input type="hidden" name="action" value="crm_merge_duplicate_students">
	<input type='submit' value='Delete Duplicate'></form><?php
}

function crm_merge_duplicate_students(){
	global $wpdb;
	$student1 = new Akimbo_Crm_Student($_POST['student1']);
	$student2 = new Akimbo_Crm_Student($_POST['student2']);
	
	/* Update attendance table */
	$classes = $student2->get_classes();
	if($classes != NULL){
		$table = $wpdb->prefix.'crm_attendance';
		$where = array ('student_id' => $student2->get_id());
		foreach($classes as $class){
			$data = array(
				'student_id' => $student1->get_id(), 
				'user_id' => $student1->get_user_id(), 
				'student_name' => $student1->full_name(),
			);
			$wpdb->update( $table, $data, $where);
		}
	}

	/* Compare student details table */
	$student1_info = $student1->get_student_info();
	$student2_info = $student2->get_student_info();
	$dob = ($student1_info->student_dob <= 1) ? $student2_info->student_dob : $student1_info->student_dob;
	$start = ($student1_info->student_startdate >= $student2_info->student_startdate) ? $student1_info->student_startdate : $student2_info->student_startdate;
	$waiver = ($student1_info->student_waiver >= $student2_info->student_waiver) ? $student1_info->student_waiver : $student2_info->student_waiver;
	$table = $wpdb->prefix.'crm_students';
	$where = array ('student_id' => $student1->get_id());
	$data = array(
		'student_dob' => $dob, 
		'student_waiver' => $waiver,
		'student_startdate' => $start, 
	);
	$data['student_notes'] = ($student1_info->student_notes == NULL || $student2_info->student_notes != NULL) ? $student2_info->student_notes : $student1_info->student_notes;
	$data['marketing'] = ($student1_info->marketing == NULL || $student2_info->marketing != NULL) ? $student2_info->marketing : $student1_info->marketing;
	$wpdb->update( $table, $data, $where);

	/* Delete student 2*/
	$result = $wpdb->delete( $table, array( 
		'student_id' => $_POST['student2'],) 
	);

	wp_redirect($student1->student_admin_link()."&message=success" ); 
	exit;
}

/*
*
* crm_student_dropdown(): dropdown list of all students, ordered by first name. Value = student id
*
*/
function crm_student_dropdown($name = "student", $exclude = NULL){//takes array of ids
	global $wpdb;
	$exclude = ($exclude != NULL) ? $exclude : array();
	echo "<select name= '".$name."'><option value='0'><i>Select student</i></option>";
	$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students ORDER BY student_firstname");
	foreach ($students as $student){ 
		if(in_array($student->student_id, $exclude)){}else{
			?><option value="<?php echo $student->student_id;?>"><?php echo $student->student_firstname." ".$student->student_lastname;?></option><?php 
		}
	}
	echo "</select>";
}


function akimbo_crm_get_students($age = NULL, $status = 'all', $semester_slug = NULL){//kids, all/current/not_returning
	global $wpdb;
	$students = $wpdb->get_col("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_firstname");
	$semester = akimbo_term_dates('return');//do this either way
	if($semester_slug == NULL){
		$semester_slug = $semester['slug'];
	}
	$past_slug = $semester['previous'];//akimbo_previous_semester($semester_slug);
	foreach($students as $student_id){
		$student = new Akimbo_Crm_Student($student_id);
		if($age != NULL){
			if($student->get_age() == $age){$list[] = $student;}
		}else{$list[] = $student;}
	}
	if($status != 'all' && $list != NULL){
		foreach($list as $listed_student){
			$student = new Akimbo_Crm_Student($listed_student->student_id);
			if($student->attending_this_semester($semester_slug) == true){
				$current[] = $student;
			}elseif($student->attending_this_semester($past_slug) == true){
				$not_returning[] = $student;
			}
		}			
	}
	if($status == 'current'){
		$list = $current;
	}elseif($status == "not_returning"){
		$list = $not_returning;
	}
	return $list;
}

function update_student_details_form($id = NULL, $url = NULL, $admin = NULL){//$admin = backend, set to 1
	if($id != NULL){
		$student = new Akimbo_Crm_Student($id);
		$info = $student->get_student_info();
		echo "<h3>".$student->full_name()."</h3>";
		echo "Contact number: ".$student->contact_phone();
	}else{
		echo "<h3>Add New Student</h3>";
	}
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<br/>Student first name: <input type="text" name="student_firstname" <?php if($id != NULL){ ?> value="<?php echo $info->student_firstname;?>" <?php } ?> required>
	<br/>Student last name: <input type="text" name="student_lastname" <?php if($id != NULL){ ?> value="<?php echo $info->student_lastname;?>" <?php } ?> >
	<br/>Student DOB: <input type="date" name="student_dob" <?php if($id != NULL){ ?> value="<?php echo $info->student_dob;?>" <?php } ?> >
	<br/>Relationship to you: <small><i>Please enter "user" if enrolling yourself</i></small><input type="text" name="student_rel" <?php if($id != NULL){ ?> value="<?php echo $info->student_rel;?>" <?php } else { ?> placeholder="e.g. daughter, brother, user" <?php } ?> >
	<br/>Do you have any current or recurring injuries, allergies, medical conditions, or anything else we should be aware of?
	<br/><input type="text" name="student_notes" <?php if($id != NULL){ ?> value="<?php echo $info->student_notes;?>" <?php } ?> >
	<br/>Where did you hear about us?
	<br/><input type="text" name="marketing" <?php if($id != NULL){ ?> value="<?php echo $info->marketing;?>" <?php } ?> >
	<?php if($id != NULL){ ?> <input type="hidden" name="update" value="1"><input type="hidden" name="student_id" value="<?php echo $info->student_id; ?>"> <?php } 
	if(is_user_logged_in()){
		if(current_user_can( 'manage_options' )){
			echo "<br/>Managing User: ";
			$user_id = ($id != NULL) ? $info->user_id : 1;
			akimbo_user_dropdown("user_id", $user_id);
			if($user_id >=2){echo $student->user_admin_link("View Managing User");}
		}else{//not admin, use user id
			?><input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>"> <?php
		}
	}else{//not logged in, default 1
		?><input type="hidden" name="user_id" value="1"> <?php
	}
	if($info->student_waiver >= 1){ 
		echo "<small><br/>Waiver signed ".date("g:ia, l jS M Y", strtotime($info->student_waiver))."</small>";//don't show if already signed
	} else{ //don't show for admin? //elseif(!current_user_can('manage_options'))
		if($admin != NULL){
			?><br/><b>Waiver Not Signed</b><?php
		}else{
			$time = current_time('Y-m-d H:ia');
			?><br/><br/><b>Participation Release of Liability & Assumption of Risk Agreement</b>
			<div style="overflow: auto; height:200px;">
			<br/><small>In consideration of being allowed to participate in any way in the program, related events and activities, and use of equipment, I <?php //echo $customer->first_name." ".$customer->last_name.", ";?>acknowledge, appreciate, and agree that: 
			<br/>1. The risk of injury from the activities involved in this program is significant, including the potential for permanent paralysis and death. 
			<br/>2. I knowingly and freely assume all such risks, both known and unknown, even if arising from the negligence of the releases or others, and assume full responsibility for my participation. 
			<br/>3. I willingly agree to comply with terms and conditions for participation. If I observe any unusual significant hazard during my presence or participation, I will remove myself from participation and bring such to the attention of the nearest official immediately. 
			<br/>4. I, for myself and on behalf of my heirs, assigns, personal representatives and next of kin, hereby release, indemnify, and hold harmless Circus Akimbo, its officers, officials, agents and/or employees, other participants, sponsors, advertisers, and, if applicable, owners and lessors of premises used to conduct the event (releases), from any and all claims, demands, losses, and damages to person or property, whether arising from the negligence of the releases or otherwise, to the fullest extent permitted by law. 
			<br/>Health Statement: I will notify Circus Akimbo ownership or employees if I suffer from any medical or health condition that may cause injury to myself, others, or may require emergency care during my participation. To the best of my knowledge, all information contained on this sheet is correct. In the case of a medical incident, I give permission for Circus Akimbo staff to provide required first aid. If necessary, this may include calling an ambulance, the cost of which is to be covered by the student.
			<br/>Media Statement: I agree that Circus Akimbo may use my name, image, voice, or statements including any and all photographic images and video or audio recordings made by Circus Akimbo. </small>
			</div>

			<br/><input type="checkbox" name="waiver" value="<?php echo $time; ?>" <?php if($admin == NULL){echo "required";} ?> >I have read this release of liability and assumption of risk agreement, and agree to the terms.
			<?php 	
		}
	}	

	
	if($url == NULL){//send back to enrolment page
		$url = get_site_url()."/enrolment?";
	}  ?>
	<input type="hidden" name="referral_url" value="<?php echo $url; ?>">
	<input type="hidden" name="action" value="crm_add_student">
	<br/><br/><input type="submit" value="Update Student Details"> <!--<input type="submit" value="Save and add new">-->
	</form>
	<!--End student details form -->
	
	<?php
}

function crm_add_student(){//values set on add_family_form.php
	global $wpdb;
		$table = $wpdb->prefix.'crm_students';
		$student_firstname = sanitize_text_field($_POST['student_firstname']);
		$student_lastname = sanitize_text_field($_POST['student_lastname']);
		$student_dob = sanitize_text_field($_POST['student_dob']);
		$student_rel = sanitize_text_field($_POST['student_rel']);
		$student_rel = strtolower("$student_rel");//lower field so can be recognised by code checking for "user"
		$student_notes = sanitize_text_field($_POST['student_notes']);
		$marketing = sanitize_text_field($_POST['marketing']);
		$data = array(
			'student_firstname' => $student_firstname,
			'student_lastname' => $student_lastname,
			'student_dob' => $student_dob,
			'student_rel' => $student_rel,
			'student_notes' => $student_notes,
			'marketing' => $marketing,
		);
		$user = $_POST['user_id'];
		$waiver = $_POST['waiver'];
		if(!$_POST['update']){ //new student
			/*$data += ['student_waiver' => $waiver, 'user_id' => $user];
			$wpdb->insert($table, $data);*/
			if($_POST['waiver']){$data += ['student_waiver' => $waiver];}
			$data += ['user_id' => $user];
			$wpdb->insert($table, $data);
			$student_id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ");
		} else { //update details
			if($_POST['waiver']){//only update if shown, won't be posted if already submitted to avoid wiping waiver data on updates
				$data += ['student_waiver' => $waiver,  'user_id' => $user, ];
			}
			//else {//avoid wiping waiver data on updates
				$data += ['user_id' => $user, ];
				$student_id = $_POST['student_id'];
				//$old_user = $wpdb->get_var("SELECT user_id FROM {$wpdb->prefix}crm_students WHERE student_id = '$student_id'");
				//update user in attendance table
				$attendance = $wpdb->prefix.'crm_attendance';
				$wpdb->update( $attendance, array('user_id' => $user,'student_name' => $student_firstname,), array ('student_id' => $_POST['student_id']));
			//}
			$where = array ('student_id' => $_POST['student_id']);
			$wpdb->update( $table, $data, $where);
			$student_id = $_POST['student_id'];
		}
	
	if($_POST['referral_url']){$url = $_POST['referral_url']."&student=".$student_id."&message=success";}else{$url = get_permalink( get_option('woocommerce_myaccount_page_id') )."&message=success";}
	wp_redirect( $url ); 
	exit;
}

/**
 *
 * User dashboard class enrol button. Replaces adult_enrol_button in version 2.0.
 * Only shown to users with active passes
 * 
 */
function crm_casual_enrol_button($student, $class, $order, $url = NULL, $age = NULL){//Front end
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="order" value="<?php echo $order; ?>">
	<input type="hidden" name="student" value="<?php echo $student; ?>">
	<input type="hidden" name="class" value="<?php echo $class; ?>">
	<input type="hidden" name="age" value="<?php echo $age; ?>">
	<?php // if($url != NULL){ <input type="hidden" name="url" value="<?php echo $url; "> <?php } ?>
	<input type="hidden" name="action" value="update_casual_enrolment">
	<input type="submit" value="Sign Up"></form><?php 
}

/**
 *
 * Enrol student into a casual class (front end). Requires item id, student id & class id. Values set on crm_dashboard
 * 
 */
function update_casual_enrolment() {//values set on crm_dashboard.php, student, class & order id given
		global $wpdb;
		$student = $_POST['student'];
		$class = $_POST['class'];
		$student = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_students WHERE student_id = $student");
		$student_user = $student->user_id;
		$student_name = $student->student_firstname." ".$student->student_lastname;
		
		$item_id = $_POST['order'];
		if($item_id <= 1){
			$age = (isset($_POST['age'])) ? $_POST['age'] : NULL;
			$current_order = casual_return_available_sessions($student_user, $age);
			if($current_order['item_id'] != NULL){
				$item_id = $current_order['item_id'];
			}else{$item_id = 0;}		
		}
		if($item_id > 1){
			$sessions = wc_get_order_item_meta($item_id, "pa_sessions");
			if(!$sessions){$sessions = wc_get_order_item_meta($item_id, "sessions");}
			$quantity = wc_get_order_item_meta($item_id, "_qty");
			$total_sessions = $sessions*$quantity;
			$sessions_used = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");
			if(!isset($sessions_used)){$sessions_used = 0;}
		}

		$table = $wpdb->prefix.'crm_attendance';
		$data = array(
			//'class_list_id' => 1,
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
		if( FALSE === $result ) {$message = "error";
		}elseif($item_id <= 1){$message = "success";//don't update an order that doesn't exist
		} else {$message = "success";
			$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");//sessions_used
			$meta_key = "sessions_used";
			$update = wc_get_order_item_meta($item_id, "sessions_used");
			if(!isset($update)){
			//$sessions_used <= 0
				wc_add_order_item_meta($item_id, $meta_key, $meta_value);
			} else {
				wc_update_order_item_meta($item_id, $meta_key, $meta_value);
			}
			
			//update Mailchimp most recent class
			$class_id = $_POST['class'];
			$value = $wpdb->get_var("SELECT session_date FROM {$wpdb->prefix}crm_class_list WHERE list_id = $class ");
			$user_info = get_userdata($student_user);
			$email = $user_info->user_email;
			akimbo_crm_mailchimp_update_merge_field("ENDDATE", $value, $email);
		}
	
	$url = (isset($_POST['url'])) ? get_site_url().$_POST['url']."&message=".$message : get_permalink( wc_get_page_id( 'myaccount' ) );	
	wp_redirect( $url ); 
	exit;	
}

/**
 *
 * Enrol students as unpaid. Eventually search for orders and automatically assign if they match
 * 
 */
function akimbo_crm_admin_manual_enrolment(){		
	//update crm_attendance
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	$student_id = $_POST['student'];
	$student_name = $wpdb->get_var("SELECT student_firstname FROM {$wpdb->prefix}crm_students WHERE student_id = '$student_id'");
	$student_user = $wpdb->get_var("SELECT user_id FROM {$wpdb->prefix}crm_students WHERE student_id = '$student_id'");

	$data = array(
		'class_list_id' => $_POST['class'],
		'user_id' => $student_user,
		'student_id' => $student_id,
		'student_name' => $student_name,
		'enrolled' => '1',
		);
		$wpdb->insert($table, $data);
		
	$admin_notice = "success";
	
	if(isset($_POST['url'])){
		$url = $_POST['url'];
	}else {
		$url = akimbo_crm_class_permalink($_POST['class_id']);
	}
	
	wp_redirect( $url ); 
	exit;
}

function kids_class_enrolment(){//matched orders, where item_id is given	
	//update crm_attendance
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	/**
	 * Get Student Info
	 */
	$student_id = $_POST['student_id'];
	$student = new Akimbo_Crm_Student($student_id);
	/**
	 * Get Class info
	 */
	$class = new Akimbo_Crm_Class($_POST['class_id']);
	$class_info = $class->get_class_info();
	$attendance_array = $student->class_attendance();//array of attended class ids
	$classes = ($_POST['start_date'])? $class->enrolment_related_classes('all', $_POST['start_date']) : $class->enrolment_related_classes('all');
	/**
	 * Get pass info
	 */
	$item_id = $_POST['item_id'];
	$item_info = crm_get_item_available_passes($item_id);
	$remaining = $item_info['remaining'];

	foreach($classes as $enrol_class){
		$data = array(
		'class_list_id' => $enrol_class->class_id,
		'user_id' => $_POST['customer_id'],
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

	//$meta_value = $weeks_used+$x;
	$meta_value = crm_calculate_passes_used($item_id);//count rows in att table using item_id
	if($result){
		$message="success";
		wc_update_order_item_meta($item_id, "weeks_used", $meta_value);
		//update Mailchimp most recent class
		$user = $_POST['customer_id'];
		$user_info = get_userdata($user);
		$email = $user_info->user_email;
		akimbo_crm_mailchimp_update_merge_field("ENDDATE", $enddate, $email);
	}else{$message="error";}
	
	
	$url = akimbo_crm_class_permalink($finalclass)."&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/**
 *
 * Unenrolment
 * 
 */

//Unenrol single student, with attendance ID provided
function crm_student_unenrol_button($att_id, $class_type = NULL){
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
		if($class_type != NULL){echo "<input type='hidden' name='class_type' value='".$class_type."'>";}
		?><input type="hidden" name="att_id" value="<?php echo $att_id; ?>"> 
		<input type="hidden" name="action" value="unenrol_button">
		<input type="submit" name="submit" value="Unenrol">
	</form> <?php
}

//Student dropdown list with unenrolment button. Takes an array of student objects
function crm_admin_unenrolment_button($class_id, $student_list, $class_type = "casual"){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<select name= 'att_id'><option value="">Select student</option>
	<?php foreach ($student_list as $student){ 
		?><option value="<?php echo $student->att_id;?>"><?php echo $student->full_name(); ?></option><?php
	} 
	?></select> 
	<input type="hidden" name="class_id" value="<?php echo $class_id;?>">
	<input type="hidden" name="class_type" value="<?php echo $class_type;?>">	
	<input type="hidden" name="action" value="unenrol_button">
	<input type="submit" name="submit" value="Unenrol">
	</form><?php //<input type='submit' value='Late Cancel'> //Remove option - students are either given pass back or kept in class for record keeping purposes
}

function crm_unenrolment_process(){//early cancel
	global $wpdb;
	$att_id = $_POST['att_id'];
	$attendance = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_attendance 
		LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id 
		WHERE attendance_id = $att_id
		LIMIT 1");
	
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
	}else {$url = get_permalink( wc_get_page_id( 'myaccount' ) );}

	//return pass if ord_id >1 & not late cancelled session
	if($_POST['submit'] != "Late Cancel"){//return pass
		$item_id = $attendance->ord_id;
	}
	
	//update order_itemmeta
	if( FALSE === $result ) {//echo failure message
		$url .= "?&message=failure";
	} elseif(!isset($item_id) || $item_id == 999999){//don't do anything else for unpaid orders	
	} else {
		if(isset($_POST['class_type'])){
			$class_type = $_POST['class_type'];
		}else{
			$class_id = $attendance->class_list_id;
			$class = new Akimbo_Crm_Class($class_id);
			$class_type = $class->get_class_type();
		}
		$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");//sessions_used
		$age = $attendance->age_slug;
		$meta_key = ($class_type == "enrolment") ? "weeks_used" : "sessions_used";
		wc_update_order_item_meta($item_id, $meta_key, $meta_value);
	}
	
	wp_redirect( $url ); 
	exit;	
}



/**
 *
 * Change attendance ID for a given student to move them into another class
 * 
 */

function akimbo_crm_swap_student(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	$data = array('class_list_id' => $_POST['class_id'],);
	$where = array('attendance_id' => $_POST['att_id']);
	$wpdb->update( $table, $data, $where); 
	$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$_POST['class_id'];
	wp_redirect( $url ); 
	exit;	
}




/**
 *
 * Potentially outdated
 * 
 */

/*************
Reference list
**************
adult_enrolment_form() 
crm_admin_add_new_student()

*/
//possibly outdated function? Moved from enrolment functions
add_action( 'admin_post_adult_add_enrolment', 'adult_enrolment_form' );
function adult_enrolment_form() {		
		//update crm_students		
		global $wpdb;
		$table = $wpdb->prefix.'crm_students';
		$data = array(
			'user_id' => $_POST['user_id'],
			'student_firstname' => $_POST['student_firstname'],
			'student_lastname' => $_POST['student_lastname'],
			'student_dob' => $_POST['student_dob'],
			);
		$wpdb->insert($table, $data);
		
		$admin_notice = "success";
		$site = get_site_url();
		$url = $site."/account";
		wp_redirect( $url ); 
		exit;	
}



function crm_admin_add_new_student(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_students';
	$data = array(
		'user_id' => $_POST['customer_id'],
		'student_firstname' => $_POST['student'],
		);
	$wpdb->insert($table, $data);
	
	// redirect to bookings page
	$site = get_site_url();
	$url = $site."/wp-admin/admin.php?page=akimbo-crm2&class=".$_POST['class'];

	wp_redirect( $url ); 
	exit;	
}

function admin_assign_order_id(){		
	//update crm_attendance
	global $wpdb;
	$table = $wpdb->prefix.'crm_attendance';
	//$where = array('class_list_id' => $_POST['class_id'],'student_id' => $_POST['student_id'],);
	$where = array('attendance_id' => $_POST['att_id']);
	$data = array('ord_id' => $_POST['item_id']);
	$wpdb->update( $table, $data, $where);
	
	//update sessions
	$item_id = $_POST['item_id'];
	$casual = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id' AND meta_key = 'pa_sessions'"); 
	$enrolment = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = '$item_id' AND meta_key = 'weeks'"); 
	if($casual){
		$meta_key = "sessions_used";
	}elseif($enrolment){
		$meta_key = "weeks_used";
	}
	$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");
	wc_update_order_item_meta($item_id, $meta_key, $meta_value);
		
	$admin_notice = "success";
	$ref = $_POST['referral_url'];
	$url = ($ref != NULL) ? $ref : akimbo_crm_class_permalink($_POST['class_id']);
	
	wp_redirect( $url ); 
	exit;
}

add_action( 'admin_post_casual_unenrol_button', 'crm_early_cancel_unenrol' );//hopefully outdated
add_action( 'admin_post_enrolment_unenrol_button', 'crm_early_cancel_unenrol' );//hopefully outdated crm_enrolment_unenrol_process
function crm_casual_unenrolment(){//early cancel
	global $wpdb;
	$att_id = $_POST['att_id'];
	$attendance = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_attendance WHERE attendance_id = $att_id");
	if($_POST['submit'] != "Late Cancel"){$item_id = $attendance->ord_id;}
	$class_id = $attendance->class_list_id;
	
	//update CRM attendance
	$table = $wpdb->prefix.'crm_attendance';
	$result = $wpdb->delete( $table, array( 
		'attendance_id' => $_POST['att_id'],) 
	);
	
	//update order_itemmeta
	if( FALSE === $result ) {//echo failure message
	} elseif(!isset($item_id) || $item_id == 999999){//don't do anything else for unpaid orders	
	} else {
		$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");//sessions_used
		$meta_key = "sessions_used";
		wc_update_order_item_meta($item_id, $meta_key, $meta_value);
	}
	
	if(isset($_POST['url'])){ $url = get_site_url().$_POST['url'];
	}else {$url = get_permalink( wc_get_page_id( 'myaccount' ) );}
	wp_redirect( $url ); 
	exit;	
}

/**
 *
 * Unenrol student from a single session of an enrolment
 * 
 */
function crm_enrolment_unenrol_process() {//renamed kids_unenrolment
	global $wpdb;
	$att_id = $_POST['att_id'];
	$attendance = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_attendance WHERE attendance_id = $att_id");
	$item_id = $attendance->ord_id;
	$class_id = $attendance->class_list_id;
	
	//update CRM attendance
	$table = $wpdb->prefix.'crm_attendance';
	$result = $wpdb->delete( $table, array( 
		'attendance_id' => $_POST['att_id'],) 
	);
	
	//update order_itemmeta
	if( FALSE === $result ) {//echo failure message
	} elseif(!isset($item_id)){//don't do anything else for unpaid orders	
	} else {
		$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");//sessions_used
		$meta_key = "weeks_used";
		wc_update_order_item_meta($item_id, $meta_key, $meta_value);
	}
	
	if(isset($_POST['url'])){ $url = get_site_url().$_POST['url'];
	}else {$url = get_permalink( wc_get_page_id( 'myaccount' ) );}
	wp_redirect( $url ); 
	exit;
}