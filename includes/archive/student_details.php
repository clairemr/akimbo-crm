<?php
global $wpdb;
echo "<br/>";
crm_dropdown_selector("students", "akimbo-crm", "details");
crm_dropdown_selector("users", "akimbo-crm", "details");
akimbo_crm_student_permalink("new", "Add new student");
if(isset($_GET['student'])){
	if($_GET['student'] == "new"){
		echo "<h3>Add New Student</h3>";
		if(isset($_GET['class'])){//redirect to manage classes page
			$url = akimbo_crm_class_permalink($_GET['class']);
		}else{
			$student_id = $wpdb->get_var("SELECT student_id FROM {$wpdb->prefix}crm_students ORDER BY student_id DESC LIMIT 1 ") + 1;
			$url = akimbo_crm_student_permalink($student_id);
		}
		update_student_details_form(0, $url, 1);
	}else{
		$student = new Akimbo_Crm_Student($_GET['student']);
		$info = $student->get_student_info();
		echo "<h3>".$info->student_firstname." ".$info->student_lastname."</h3>";
		echo "Contact number: ".$student->contact_phone();
		update_student_details_form($student->get_id(), akimbo_crm_student_permalink($student->get_id()), 1);
	}
	echo "<br/><hr><br/>";
	if(isset($student)){
		$student_classes = $student->get_classes();
		if(!$student_classes){
			echo "No classes found. Delete student?";
			crm_simple_delete_button("crm_students", "student_id", $student_id, akimbo_crm_student_permalink());
		}else {
			echo "<h2>Classes Attended</h2>";
			echo "<table><tr><td>Date</td><td>Class</td><td>Attended</td><td>Order Item</td></tr>";
			foreach($student_classes as $student_class){
				echo "<tr><td>".date("g:ia, l jS M Y", strtotime($student_class->session_date));
				echo "</td><td>".akimbo_crm_class_permalink($student_class->class_list_id, $student_class->class_title);
				echo "</td><td>".$student_class->attended;
				echo "</td><td><a href='".crm_order_link_from_item_id($student_class->ord_id)."'>".$student_class->ord_id."</a></td><td>";
				crm_simple_delete_button("crm_attendance", "attendance_id", $student_class->attendance_id, $student->student_admin_link());
				echo "<i>does not return pass</i></td></tr>";
			}
			echo "</table>";
			akimbo_crm_student_details_mailchimp_integration($student);
		}
	}	
}//end student details

/**
* Sort by user
*/
if(isset($_GET['user'])){
	$user_id = $_GET['user'];
	$user_info = get_userdata($user_id);
	echo '<h3>User: ' . $user_info->display_name."</h3>";
	echo 'Username: ' . $user_info->user_login . "<br/>Name: ". $user_info->first_name." ".$user_info->last_name;
	echo "<br/>Email: ".$user_info->user_email;
	$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = '$user_id' ");
	if(!$students){echo "No students found";} else{
		foreach ($students as $student){
			echo "<h3>".$student->student_firstname." ".$student->student_lastname;
			akimbo_crm_student_permalink($student->student_id, "View");
			echo "</h3>";
		}
	}
	echo "<a href='".get_site_url()."/wp-admin/user-edit.php?user_id=".$user_id."'><button>View Full User Profile</button>";
}