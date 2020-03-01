<?php

if(isset($_GET['semester'])){
	echo "<h2>".$_GET['semester']."</h2>";
}else{
	$current_semester = akimbo_term_dates('return', current_time('Y-m-d-h:ia'));
	echo "<h2>".$current_semester['slug'].": <small>".$current_semester['start']." - ".$current_semester['end']."</small></h2>";
}
$students = $wpdb->get_col("SELECT student_id FROM {$wpdb->prefix}crm_students");
$slug = (isset($_GET['semester'])) ? $_GET['semester'] : $current_semester['slug'];
$past_slug = akimbo_previous_semester($slug);
foreach($students as $student_id){
	$student = new Akimbo_Crm_Student($student_id);
	$student_list[] = $student_id;
	$age = $student->get_age();
	if($student->attending_this_semester($slug) == true && $student->attending_this_semester($past_slug) == true){
		$returning[] = $student;
	}elseif($student->attending_this_semester($slug) == true && $student->attending_this_semester($past_slug) != true){
		$new[] = $student;
	}elseif($student->attending_this_semester($slug) != true && $student->attending_this_semester($past_slug) == true){
		$not_returning[] = $student;
	}else{//haven't enrolled
		$other[] = $student;
	}
}
//echo "<br/><i>Total students: ".count($student_list);
$previous_term_students = count($returning) + count($not_returning);
$current_term_students = count($returning) + count($new);
echo "<i>Previous term students: ".$previous_term_students." (".count($returning)." returning, ".count($not_returning)." not returning)";
echo "<br/>Current term students: ".$current_term_students." (".count($new)." new, ".count($returning)." returning)</i>";
if(count($returning) >=1 && $previous_term_students){
	$retention = count($returning)/$previous_term_students;
	echo "<br/><i>Retention: ".round($retention * 100 )."%</i>";
}

echo "<hr>";
akimbo_crm_manage_mailchimp_integration("akimbo-crm3", "statistics");
echo "<hr>";
/*$students = $wpdb->get_col("SELECT distinct(student_id) FROM {$wpdb->prefix}crm_attendance 
LEFT JOIN {$wpdb->prefix}crm_class_list ON {$wpdb->prefix}crm_attendance.class_list_id = {$wpdb->prefix}crm_class_list.list_id
ORDER BY student_name ASC
");*/



echo "<table width='100%'><tr>
	<th width='33%'>Returning students: ".count($returning)."</th>
	<th width='34%'>New students: ".count($new)."</th>
	<th width='33%'>Students who haven't re-enrolled: ".count($not_returning)."</th></tr>";

echo "<td valign='top'>";
if($returning){
	foreach ($returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$emails[] = $student->contact_email();
	}
	echo "<hr><br/>Emails: ";
	foreach($emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td><td valign='top'>";
if($new){
	foreach ($new as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$emails[] = $student->contact_email();
	}
	echo "<hr><br/>Emails: ";
	foreach($emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td><td valign='top'>";

if($not_returning){
$kids_emails = array();
	foreach ($not_returning as $student){
		$details = $student->get_student_info();
		echo "<br/>".$details->student_firstname." ".$details->student_lastname."<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=details&student=".$details->student_id."'> View</a>";
		$contact = $student->contact_email();
		$emails[] = $student->contact_email();
		if($student->get_age() == 'kids' && !in_array($contact, $kids_emails)){
			$kids_emails[] = $student->contact_email();
		}
	}
	echo "<hr><br/>Emails: ";
	foreach($emails as $email){echo $email.", ";}
	echo "<hr><br/>Kids Emails: ";
	foreach($kids_emails as $email){echo $email.", ";}
	unset($emails);
}
echo "</td></tr></table>";



//purchases up to end of semester count towards that term. From the next day it counts towards following term

		
//Total number of students
//Unique students who attended a class in T3-2018
//Unique students who attended a class in T4-2018
//Students who attended their first class in T4-2018 (NEW)
//Students who attended a class in T4-2018 and also a previous term (RE)

