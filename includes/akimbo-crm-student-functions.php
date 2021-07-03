<?php //Crm Student Functions

/***********************************************************************************************
 * 
 * Add and Update Student Functions 
 * 
 ***********************************************************************************************/

function update_student_details_form($id = NULL, $url = NULL, $admin = NULL){//$admin = backend, set to 1
	if($id != NULL && $id != "new"){
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
	<br/>Student DOB: <input type="date" name="student_dob" <?php if($id != NULL){ ?> value="<?php echo $student->get_dob('Y-m-d');?>" <?php } ?> >
	<br/>Relationship to you: 
	<select name="student_rel">
		<?php 
		if($id != NULL){ 
			if($info->student_rel == "user"){
				echo "<option value='user'>Myself</option>";
			} else{
				echo "<option value='".$info->student_rel."'>".ucfirst($info->student_rel)."</option>";
			}
			echo "<option>____________</option>";
		} ?>
		<option value="user">Myself</option>
		<option value="child">My Child</option>
		<option value="relative">Other relative</option>
		<option value="friend">Friend</option>
	</select>
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
			if(isset($student)){echo $student->user_admin_link("View Managing User");}
		}else{//not admin, use user id
			?><input type="hidden" name="user_id" value="<?php echo get_current_user_id(); ?>"> <?php
		}
	}else{//not logged in, default 1
		?><input type="hidden" name="user_id" value="1"> <?php
	}
	if(isset($info) && $info->student_waiver >= 1){ 
		echo "<small><br/>Waiver signed ".date("g:ia, l jS M Y", strtotime($info->student_waiver))."</small>";//don't show if already signed
	} else{
		if($admin != NULL){//don't allow admin to accidentally sign
			?><br/><b>Waiver Not Signed</b><?php
		}else{
			$time = current_time('Y-m-d H:ia');
			?><p><b>Participation Release of Liability & Assumption of Risk Agreement</b></p>
			<div style="overflow: auto; height:200px;"><br/><small><?php
			$display = "In consideration of being allowed to participate in any way in the program, related events and activities, and use of equipment, I ";
			//echo $customer->first_name." ".$customer->last_name.", ";
			$display .= "acknowledge, appreciate, and agree that:";
			$display .= "<br/>1. The risk of injury from the activities involved in this program is significant, including the potential for permanent paralysis and death.";
			$display .= "<br/>2. I knowingly and freely assume all such risks, both known and unknown, even if arising from the negligence of the releases or others, and assume full responsibility for my participation.";
			$display .= "<br/>3. I willingly agree to comply with terms and conditions for participation. If I observe any unusual significant hazard during my presence or participation, I will remove myself from participation and bring such to the attention of the nearest official immediately.";
			$display .= "<br/>4. I, for myself and on behalf of my heirs, assigns, personal representatives and next of kin, hereby release, indemnify, and hold harmless Circus Akimbo, its officers, officials, agents and/or employees, other participants, sponsors, advertisers, and, if applicable, owners and lessors of premises used to conduct the event (releases), from any and all claims, demands, losses, and damages to person or property, whether arising from the negligence of the releases or otherwise, to the fullest extent permitted by law.";
			$display .= "<br/><b>Health Statement:</b> I will notify Circus Akimbo ownership or employees if I suffer from any medical or health condition that may cause injury to myself, others, or may require emergency care during my participation. To the best of my knowledge, all information contained on this sheet is correct. In the case of a medical incident, I give permission for Circus Akimbo staff to provide required first aid. If necessary, this may include calling an ambulance, the cost of which is to be covered by the student.";
			$display .= "<br/><b>Media Statement:</b> I agree that Circus Akimbo may use my name, image, voice, or statements including any and all photographic images and video or audio recordings made by Circus Akimbo. </small>";
			echo apply_filters('akimbo_crm_student_waiver_form_text', $display);
			echo "</div><br/>"; ?>
			<input type="checkbox" name="waiver" value="<?php echo $time; ?>" <?php if($admin == NULL){echo "required";} ?> >I have read this release of liability and assumption of risk agreement, and agree to the terms.
			<?php 	
		}
	}?>
	
	<input type="hidden" name="action" value="crm_add_student">
	<input type="hidden" name="referral_url" value="<?php echo $url; ?>">
	<br/><br/><input type="submit" value="Update Student Details"> <!--<input type="submit" value="Save and add new">-->
	</form>
	<!--End student details form -->
	
	<?php
}

add_action( 'admin_post_crm_add_student', 'crm_add_student' );
add_action( 'admin_post_nopriv_crm_add_student', 'crm_add_student' );
//add_action( 'admin_post_admin_add_new_student', 'crm_add_student' );//double check then delete

/*
 * Action for update_student_details_form
 * also replaces admin_add_new_student (used in matched orders function)
*/
function crm_add_student(){
	global $wpdb;
	$table = $wpdb->prefix.'crm_students';
	$student_firstname = sanitize_text_field($_POST['student_firstname']);
	$student_lastname = sanitize_text_field($_POST['student_lastname']);
	if(!isset($_POST['class'])){
		$data = array(
			'student_firstname' => $student_firstname,
			'student_lastname' => $student_lastname,
			'student_dob' => $_POST['student_dob'],
			'student_rel' => strtolower(sanitize_text_field($_POST['student_rel'])),
			'student_notes' => sanitize_text_field($_POST['student_notes']),
			'marketing' => sanitize_text_field($_POST['marketing']),
			'user_id' => $_POST['user_id'],
		);
	}else{//just the bare necessities
		$data = array(
			'student_firstname' => $student_firstname,
			'user_id' => $_POST['user_id'],
		);
	}
	
	//$waiver = $_POST['waiver'];
	if(isset($_POST['waiver']) && !isset($_POST['update'])){//check to avoid wiping waiver data on updates
		$data += ['student_waiver' => $_POST['waiver']];
	}
	
	if(!$_POST['update']){ //new student
		//Add to student table, then get new student id
		$result = $wpdb->insert($table, $data);//update student table
		$student_id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ");
	} else { //update details
		//Update student table
		$where = array ('student_id' => $_POST['student_id']);
		$result = $wpdb->update( $table, $data, $where);
		$student_id = $_POST['student_id'];
		//update attendance table
		$wpdb->update( 
			$wpdb->prefix.'crm_attendance', 
			array('user_id' => $_POST['user_id'],'student_name' => $student_firstname." ".$student_lastname,), 
			array ('student_id' => $_POST['student_id'])//updates in case of duplicates
		);
	}
	if(isset($_POST['referral_url'])){
		$url = ($_POST['referral_url'] == "students") ? akimbo_crm_account_permalinks("students", $student_id) : get_site_url()."/enrolment?"."&student=".$student_id;
	}elseif(isset($_POST['class'])){
		$url = akimbo_crm_class_permalink($_POST['class']);
	}else{
		$url = akimbo_crm_account_permalinks("students", $student_id);
	}
	$message = ($result) ? "success" : "failure";
	$url .= "&message=".$message;
	wp_redirect( $url ); 
	exit;
}

/***********************************************************************************************
 * 
 * Display Functions
 * 
 ***********************************************************************************************/
 /*
* crm_student_dropdown(): dropdown list of all students, ordered by first name. Value = student id
*/
function crm_student_dropdown($name = "student", $exclude = NULL, $user_id = NULL){//takes array of ids
	global $wpdb;
	$exclude = ($exclude != NULL) ? $exclude : array();
	$select = "<select name= '".$name."'><option value='0'><i>Select student</i></option>";
	if($user_id != null){
		$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = '$user_id'");
	}else{
		$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students ORDER BY student_firstname");
	}
	
	foreach ($students as $student){ 
		if(!in_array($student->student_id, $exclude)){
			$select .= "<option value='".$student->student_id."'>".$student->student_firstname." ".$student->student_lastname."</option>";
		}
	}
	$select .= "</select>";
	
	return $select;
}
/**
 * Display student classes, used on Student Details page. 
 * Allows unenrol, disassociate pass and delete attendance line
 */
 function akimbo_crm_display_student_classes($student){
	$student_classes = $student->get_classes("object");
	if(!$student_classes){echo "No classes found. Delete student?";
		crm_simple_delete_button("crm_students", "student_id", $student->get_id(), akimbo_crm_student_permalink());
	}else {
		echo "<h2>Classes Attended</h2>";
		echo "<table><tr><th>Date</th><th>Class</th><th>Attended</th><th>Order Item</th><th>Delete</th></tr>";
		foreach($student_classes as $student_class){
			$class_info = $student_class[0];
			$class = $student_class[1];
			echo "<tr><td>".$class->get_date()."</td><td>";
			akimbo_crm_class_permalink($class->get_id(), $class_info->class_title);
			echo "</td><td>".$class_info->attended."</td><td>";
			if($class_info->ord_id >=1){
				echo crm_order_info_from_item_id($class_info->ord_id, "url", $class_info->ord_id);
			}else{
				akimbo_crm_update_unpaid_classes($student->get_user_id(), $class_info->attendance_id, $class_info->age_slug, akimbo_crm_student_permalink($student->get_id()));
			}
			echo "</td><td>";
			crm_simple_delete_button("crm_attendance", "attendance_id", $class_info->attendance_id, akimbo_crm_student_permalink($student->get_id()));
			akimbo_crm_unenrol_student_form($class_info->attendance_id, NULL, akimbo_crm_student_permalink($student->get_id()));
			admin_disassociate_pass($class_info->ord_id, $class_info->attendance_id, akimbo_crm_student_permalink($student->get_id()));
			echo "</td></tr>";
		}
		echo "</table>";
	}
}

/**
 * Find and merge duplicate students.
 */
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
		    /*$student1 = new Akimbo_Crm_Student($student->student_id);
			$student2 = new Akimbo_Crm_Student($comparison_id);*/
		}else{
			$distinct_students[$key] = $student->student_id;
			$user_students[$student->student_firstname] = $student->student_id;
		}
		$user_id = $student->user_id;
	}
	if($duplicates == false){echo "No duplicates found";}
	echo "</details>";
}

/**
 * Merge duplicate students button
 */
function crm_merge_duplicate_students_button($student1, $student2){//discard $student2
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="student1" value="<?php echo $student1; ?>">
	<input type="hidden" name="student2" value="<?php echo $student2; ?>">
	<input type="hidden" name="action" value="crm_merge_duplicate_students">
	<input type='submit' value='Delete Duplicate'></form><?php
}

add_action( 'admin_post_crm_merge_duplicate_students', 'crm_merge_duplicate_students' );

/**
 * Merge duplicate students action
 */
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

/***********************************************************************************************
 * 
 * Update Student Orders and Passes
 * 
 ***********************************************************************************************/

/**
 * Assign orders to unpaid classes
 */
function akimbo_crm_update_unpaid_classes($user_id, $attendance_id, $age = NULL, $url = NULL){
	global $wpdb;
	$url = ($url != NULL) ? $url : akimbo_crm_permalinks("payments");
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<?php akimbo_crm_display_user_orders($user_id, $age, "item_id");?>
	<input type="hidden" name="att_id" value="<?php echo $attendance_id;?>">
	<input type="hidden" name="referral_url" value="<?php echo $url;?>">
	<input type="hidden" name="action" value="admin_assign_order_id">
	<input type='submit' value='Update'> 
	</form><?php
}

/**
 * Disassociate passes from lines in attendance table
 */
function admin_disassociate_pass($item_id, $att_id, $url = NULL){//post item_id, post att_id, optional post referral_url		
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<input type="hidden" name="item_id" value="<?php echo $item_id;?>">
	<input type="hidden" name="att_id" value="<?php echo $att_id;?>">
	<input type="hidden" name="referral_url" value="<?php echo $url;?>">
	<input type="hidden" name="disassociate" value="true">
	<input type="hidden" name="action" value="admin_assign_order_id">
	<input type='submit' value='Disassociate Pass'></form><?php
}

add_action( 'admin_post_admin_assign_order_id', 'admin_assign_order_id' );

/**
 * Assign user orders to unpaid student classes
 */
function admin_assign_order_id($item_id = NULL, $att_id = NULL, $url = NULL, $disassociate = false){//post item_id, post att_id, optional post referral_url		
	global $wpdb;
	$item_id = ($item_id != NULL) ? $item_id : $_POST['item_id'];
	if(isset($_POST['att_id'])){$att_id = $_POST['att_id'];}
	
	//$disassociate = (isset($disassociate) && $disassociate != NULL) ? $disassociate : $_POST['disassociate'];
	$disassociate = (isset($_POST['disassociate']) && $_POST['disassociate'] != NULL ) ? $_POST['disassociate']: $disassociate;
	//update crm_attendance
	if(isset($att_id) && $att_id >=1){
		$table = $wpdb->prefix.'crm_attendance';
		$where = array('attendance_id' => $att_id);
		$data = (isset($disassociate) && $disassociate == true) ? array('ord_id' => 0) : array('ord_id' => $item_id);
		$result = $wpdb->update( $table, $data, $where);
	}

	//update sessions or weeks
	if($item_id == 999999 || $item_id == NULL){//don't do this
	}else{
		$item_data = new WC_Order_Item_Product($item_id);
		if(isset($item_data['pa_sessions']) || isset($item_data['sessions'])){
			$pass_type = "sessions";
		}elseif(isset($item_data['weeks'])){
			$pass_type = "weeks";
		}
		$meta_key = $pass_type."_used";
		$meta_value = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_attendance WHERE ord_id = $item_id ");
		$result = wc_update_order_item_meta($item_id, $meta_key, $meta_value);
	}
	
	$url = (isset($_POST['referral_url']) && $_POST['referral_url'] != NULL) ? $_POST['referral_url'] : $url;
	if($url != NULL){
		$url = ($_POST['referral_url'] != NULL) ? $_POST['referral_url'] : akimbo_crm_permalinks("payments");
		$message = ($result) ? "success" : "failure";
		$url .= "&message=".$message;
		wp_redirect( $url ); 
		exit;
	}else{
		return $result;
	}
}