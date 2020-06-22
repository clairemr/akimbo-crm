<?php

/**
 * The classes functionality of the plugin.
 *
 * @link       https://circusakimbo.com.au/
 * @since      2.0
 *
 * @package    Akimbo_Crm
 * @subpackage Akimbo_Crm/classes
 * @author     Circus Akimbo <info@circusakimbo.com.au>
 */


class Akimbo_Crm_Class{

	public $class_id;
	private $class_info;
	private $class_date;
	private $class_type;
	//private $student_info;
	private $student_list;
	private $user_list;
	private $unpaid_students;

	function __construct($class_id){
		$this->class_id = $class_id;
		global $wpdb;
		$this->class_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$class_id'");
		if(isset($this->class_info)){
			if($this->class_info->age_slug == 'adult'){
				if($this->class_info->class_id < 1 ){
					$this->class_info->{'capacity'} = 18;//open training
				}elseif($this->class_info->class_id == 2 ){
					$this->class_info->{'capacity'} = 100;//virtual class
				}else{
					$this->class_info->{'capacity'} = 6;//regular class
				}
			}elseif($this->class_info->age_slug == 'kids'){
				$this->class_info->{'capacity'} = 15;
			}else{
				$this->class_info->{'capacity'} = 6;
			}
			
			$option = get_option('akimbo_crm_class_booking_window'); 
			$this->class_info->{'booking_window'} = ($option != NULL) ? $option : '+24hrs';
			$this->class_info->{'cancel_date'} = date("Y-m-d-H:i", strtotime($this->class_info->booking_window, strtotime($this->class_info->session_date)));
			//$this->class_info->{'cancel_date'} = date("Y-m-d-H:i", strtotime($this->class_info->booking_window, strtotime($this->class_info->session_date)));
		}
		
	}
	
	function get_product_id(){
		return $this->class_info->prod_id;
	}
	
	function get_class_type(){
		$class_type = crm_casual_or_enrolment($this->get_product_id());
		if($class_type == NULL){
			$class_type = ($this->class_info->age_slug == "kids") ? "enrolment" : "casual";
		}
		return $class_type;
	}

	function age_slug(){
		return $this->class_info->age_slug;
	}

	function previous_class(){
		global $wpdb;
		$previous = $this->class_id - 1;
		$id = 0;
		$info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$previous'");
		if($info != NULL){
			if($info->class_title == $this->class_info->class_title && $info->class_id == $this->class_info->class_id){$id = $info->list_id;}
		}
		return $id;
	}

	function next_class(){
		global $wpdb;
		$next = $this->class_id + 1;
		$id = 0;
		$info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE list_id = '$next'");
		if($info != NULL){
			if($info->class_title == $this->class_info->class_title && $info->class_id == $this->class_info->class_id){$id = $info->list_id;}
		}
		return $id;
	}

	function get_student_info($student_id = NULL){
		global $wpdb;
		if($student_id == NULL){
			$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance WHERE class_list_id = '$this->class_id'");
			foreach ($students as $result){
				$student = new Akimbo_Crm_Student($result->student_id);
				$student->{'att_id'} = $result->attendance_id;
				$student->{'attended'} = $result->attended;
				$student->{'ord_id'} = $result->ord_id;
				$student_list[] = $student;
				$student_ids[] = $student->student_id;
				$orders[] = $result->ord_id;
				$user_list[] = $student->get_user_id();
				$email_list[] = $student->contact_email();
				if($student->ord_id <= 1){
					$unpaid_students[] = $student->student_id;
				}
			}

			$student_info['student_list'] = (isset($student_list)) ? $student_list : array();
			$student_info['student_ids'] = (isset($student_ids)) ? $student_ids : array();
			$student_info['user_list'] = (isset($user_list)) ? $user_list : array();
			$student_info['email_list'] = (isset($email_list)) ? $email_list : array();
			$student_info['unpaid_students'] = (isset($unpaid_students)) ? $unpaid_students : array();
			$student_info['count'] = (isset($student_info['student_list'])) ? count($student_info['student_list']) : 0;
			$student_info['orders'] = (isset($orders)) ? $orders : array();
		}else{
			$student_info = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance WHERE class_list_id = '$this->class_id' AND student_id = '$student_id'");
		}

		
		
		return $student_info;
	}

	function student_count(){
		$student_info = $this->get_student_info();
		return $student_info['count'];
	}

	function email_list($format = 'return'){//user emails from student ids
		$student_info = $this->get_student_info();
		$emails = ($student_info['email_list']) ? $student_info['email_list'] : NULL;
		if($emails != NULL){
			if($format != "return"){
				$email_list = "";
				foreach($emails as $email){
					$email_list .= $email.", ";
				}
				echo $email_list;
			}else{
				return $emails;
			}
		}
		
	}
	
	function get_booking_date($format = "g:ia, l jS M"){
		$class_date = date($format, strtotime($this->class_info->session_date));
		
		return $class_date;
	}

	/**
	*
	* Update class date, front end function
	*
	*/
	function crm_update_class_date_form(){
		$date = explode(" ", $this->class_info->session_date);
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		Date: <input type="date" name="new_class_start" value="<?php echo $date[0]; ?>"><input type="time" name="new_class_time"  value="<?php echo $date[1]; ?>"> Length: <input type="number" name="duration" value="<?php echo $this->class_info->duration; ?>">
		<input type="hidden" name="id" value="<?php echo $id; ?>"><input type='submit' value='Update'>
		</form><?php
	}

	function get_class_info(){
		return $this->class_info;
	}

	function trainer_names($format = 'echo'){
		$trainers = unserialize($this->get_class_info()->trainers);
		$result = NULL;
		global $wpdb;
		if($trainers != NULL){
			foreach($trainers as $trainer){
				if($trainer >= 1){
					$name = $wpdb->get_var("SELECT display_name FROM {$wpdb->prefix}users WHERE ID = '$trainer'");
					if($format == "echo"){
						$result .= $name.", ";
					}elseif($format == "return"){
						$result[] = $name;
					}
				}
			}
		}
		return $result;
	}

	function capacity(){
		$student_info = $this->get_student_info();
		$capacity['count'] = $student_info['count'];
		$capacity['capacity'] = $this->class_info->capacity;
		$capacity['places'] = $capacity['capacity'] - $capacity['count'];
		if($capacity['count'] >= $capacity['capacity']){
			$capacity['is_full'] = true;
		}else{$capacity['is_full'] = false;}
		return $capacity;
	}

	function class_admin_link($id, $display = NULL){//text to display or link only
		$id = (!isset($id)) ? $this->class_id : $id;
		if($display != NULL){
			$url = "<a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$id."'>".$display."</a>";
		}else{
			$url = get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$id;
		}
		return $url;
	}

	function related_classes(){//used for swap student button
		global $wpdb;
		$age = $this->class_info->age_slug;
		$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE age_slug = '$age' ORDER BY session_date ASC" );
		return $classes;
	}

	function enrolment_related_classes($format = 'all'){//all, ids
		global $wpdb;
		$variation = $this->class_info->class_id;
		$semester = $this->class_info->semester_slug;
		$title = $this->class_info->class_title;//check class title so this work for casual classes too
		$result = array();
		if($semester != NULL){
			//$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation' AND semester_slug = '$semester' ORDER BY session_date ASC" );
			$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation' AND semester_slug = '$semester' AND class_title = '$title' ORDER BY session_date ASC" );
			foreach($classes as $class){
				$result[] = ($format == "ids") ? $class->list_id : new Akimbo_Crm_Class($class->list_id);
			}
		}else{
			$result[] = new Akimbo_Crm_Class($this->class_id);
		}
		
		return $result;
	}

	function enrolment_related_classes_count(){
		return count($this->enrolment_related_classes());
	}


	/*
	*
	* Display Functions
	*
	*/

	function display_related_classes(){
		global $wpdb;//use period to differentiate between future, period (all, future or semester e.g. T2-2020) and all
		$i=0;
		$class_variations = $this->enrolment_related_classes();
		//var_dump($class_variations);
		echo "<table width='80%''><tr bgcolor = '#33ccff'><th colspan='6' align='center'>".$this->class_info->semester_slug."</th></tr><tr bgcolor = '#89ccff'><th>Week</th><th>Class Name</th><th>Class Date</th><th>Enrolled</th><th>Attended</th><th>Details</th></tr>";
		foreach($class_variations as $class){
			$i++;
			$class_info = $class->get_class_info();
			echo "<tr><td>".$i."</td><td>".$class_info->class_title."</td><td>".$class_info->session_date."</td><td></td><td></td><td><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&class=".$class_info->list_id."'>View</a></td></tr>";
		}
		echo "</table>";
	}

	function display_attendance_table(){
		$class_info = $this->get_class_info();
		?><style>table td {
		    border-top: thin solid; 
		    border-bottom: thin solid;
		    border-collapse: collapse;
		}</style><?php
		echo "<br/><table width='80%' style='border-collapse: collapse;'><tr bgcolor = '#33ccff'><th colspan='3'><h2>";
		if($this->previous_class() >= 1){echo "<a href='".$this->class_admin_link($this->previous_class())."'><input type='submit' value='<'></a> ";}
		echo $class_info->class_title." ".date("g:ia, l jS M", strtotime($class_info->session_date));
		if($this->next_class() >= 1){echo  " <a href='".$this->class_admin_link($this->next_class())."'><input type='submit' value='>'></a>";	}
		echo "</h2></th>";
		$class_student_info = $this->get_student_info();
		$students = $class_student_info['student_list'];
		if($students){
			?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
			$i=0;
			foreach($students as $student){
				$i++;//increment here so it gets correct number of students
				echo "<tr><td>".$student->student_id.". ".$student->student_admin_link($student->full_name())."</td><td>";
				if($student->ord_id >= 1){ 
					echo "<a href='".crm_admin_order_link_from_item_id($student->ord_id)."'>".$student->ord_id."</a>";}else{echo "UNPAID";}
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
			<input type="hidden" name="class" value="<?php echo $this->class_id;?>">
			<input type="hidden" name="action" value="mark_attendance">
			<tr><td colspan='2'></td><td><input type='submit' value='Update Attendance'></td>
			</form><?php 
		}else{
			echo "<tr><td colspan='3'><br/>No students enrolled<br/><br/></td></tr>";
		}
		echo "</table><br/>";
	}


	function class_semester(){
		global $wpdb;
		$date = $this->class_info->session_date;
		$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_start <= '$date' AND semester_end >= '$date' LIMIT 1");
		// 
		return $semester->semester_slug;
		//return $semester;
	}

	function matched_orders(){
		global $wpdb;
		$variation_id = $this->class_info->class_id;
		$semester = $this->class_info->semester_slug;
		$items_ids = get_all_orders_items_from_a_product_variation( $this->class_info->class_id );
		$student_info = $this->get_student_info();
		//$student_ids = $student_info['student_ids'];
		$matches = false;
		//if(!$items_ids){$items_ids = get_all_orders_items_from_a_product( $product_id )}//NEW CODE
		if(isset($items_ids)){
			echo "<table width='80%''><tr bgcolor = '#33ccff'><th colspan='2'>Matched Orders</th></tr>";
			foreach( $items_ids as $item_id){
				$order_id = $wpdb->get_var("SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = $item_id");
				$order = wc_get_order($order_id);
				if($order->get_status() != "pending"  && get_post_type($order_id) != "shop_subscription"){//don't show unpaid orders or subscriptions
					$user_id = $order->get_user_id();
					$user_info = get_userdata($user_id);
					$details = crm_weeks_remaining($item_id);
					if($details['weeks'] <= 0){
						$weeks = $this->enrolment_related_classes_count();
						wc_add_order_item_meta($item_id, "weeks", $weeks);
					}
					if($details['remaining'] <= 0){//echo "used up";
					} else {//echo "show order";
						$matches = true;
						$qty = $details['qty'];
						for($i = 1; $i <= $qty; $i++){
							echo "<tr><td>Order ".$order_id.", ". wc_get_order_item_meta( $item_id, 'class-time', true );
							echo "<br/><i>Booked by " . $user_info->display_name."</i></td><td>";

							?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
							Select student: <select name= 'student_id'><?php 
							$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_students WHERE user_id = $user_id");
							foreach ($students as $student){
									$student_id = $student->student_id;
									if(in_array($student_id, $student_info['student_ids'])){//don't show
									} else {
										$student_name = $student->student_firstname;?>
										<option value="<?php echo $student_id;?>"><?php echo $student_name." ".$student->student_lastname;?></option>
									<?php }	
							} ?>
							</select> 
						
							<!--<input type="hidden" name="ord_id" value="<?php echo $order_id; ?>"><!-- update order IDs to use item id for kids too -->
							<input type="hidden" name="customer_id" value="<?php echo $user_id; ?>">
							<input type="hidden" name="admin" value="1">
							<input type="hidden" name="item_id" value="<?php echo $item_id;?>">
							<input type="hidden" name="class_id" value="<?php echo $this->class_id;?>">
							<input type="date" name="start_date">
							<input type="hidden" name="action" value="kids_enrolment_confirmation">
							
							<input type='submit' value='Enrol'> </form>
							
							
							<!--Add new student button-->
							<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
							<input type="hidden" name="customer_id" value="<?php echo $user_id; ?>">
							<input type="hidden" name="class" value="<?php echo $this->class_id;?>">
							<input type="hidden" name="action" value="admin_add_new_student">
							or <input type="text" name="student" placeholder="Student first name"> <input type='submit' value='Add New'>
							</form>
							</tr>
							<?php 
						}
					} // comment out for import purposes
				}
				
			}
			if($matches == false){echo "<tr><td colspan'2'>None found</td></tr>";}
			echo "</table>";

		}
			

	}
	
	function class_income(){
		$student_info = $this->get_student_info();
		$orders = $student_info['orders'];
		if($orders != NULL){
			$result_array = array();
			foreach($orders as $item_id){
				$price = wc_get_order_item_meta( $item_id, '_line_total', true ); 
				$qty = wc_get_order_item_meta( $item_id, '_qty', true );
				$qty = ($qty  >= 1) ? $qty : 1;
				if($this->age_slug() == "kids"){
					$denominator = wc_get_order_item_meta( $item_id, 'weeks', true );
					$denominator = ($denominator >= 1) ? $denominator : $this->enrolment_related_classes_count();
				}else{
					$denominator = wc_get_order_item_meta( $item_id, 'pa_sessions', true );
					$denominator = ($denominator >= 1) ? $denominator : wc_get_order_item_meta( $item_id, 'sessions', true );
				}
				$denominator = $denominator * $qty;
				$result += $price/$denominator;
				//$result_array[] = $price/$denominator;
			}
		}
		return $result;
	}

	function delete_all(){
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input type="hidden" name="class_id" value="<?php echo $this->class_id; ?>">
		<input type='hidden' name='action' value='crm_delete_class_series'>
		<b><input type='submit' value='Delete Series'></b></form><?php
	}


}