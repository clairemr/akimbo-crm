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
		$products = unserialize($this->class_info->prod_id);
		$result = crm_product_meta_type($products[0]);
		return $this->class_info->prod_id;
	}
	
	function get_the_title(){
		return $this->class_info->class_title;
	}

	function get_class_type(){
		/**
		 * find crm_casual_or_enrolment <-- In order functions. Hopefully outdated now. Replace with product meta data
		 */
		$class_type = crm_product_meta_type($this->get_product_id());
		//$class_type = crm_casual_or_enrolment($this->get_product_id());
		if($class_type == NULL){
			$class_type = ($this->class_info->age_slug == "kids") ? "enrolment" : "casual";
		}
		return $class_type;
	}

	function age_slug(){
		return $this->class_info->age_slug;
	}

	function get_semester(){
		return $this->class_info->semester_slug;
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
			$students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_attendance WHERE class_list_id = '$this->class_id' ORDER BY student_name");
			$student_info['attended'] = 0;
			foreach ($students as $result){
				$student = new Akimbo_Crm_Student($result->student_id);
				$student->{'att_id'} = $result->attendance_id;
				$student->{'attended'} = $result->attended;
				$student_info['attended'] += $result->attended;
				$student->{'ord_id'} = $result->ord_id;
				$student_list[] = $student;
				$student_ids[] = $student->student_id;
				$orders[] = $result->ord_id;
				$user_list[] = $student->get_user_id();
				$email_list[] = $student->contact_email();
				if($student->ord_id <= 1){
					$unpaid_students[] = $student;
				}
			}

			$student_info['student_list'] = (isset($student_list)) ? $student_list : array();
			$student_info['student_ids'] = (isset($student_ids)) ? $student_ids : array();
			$student_info['user_list'] = (isset($user_list)) ? $user_list : array();
			$student_info['email_list'] = (isset($email_list)) ? $email_list : array();
			$student_info['unpaid_students'] = (isset($unpaid_students)) ? $unpaid_students : NULL;
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

	function class_admin_link($display = NULL){//text to display or link only
		if($display != NULL){
			$url = "<a href='".akimbo_crm_class_permalink($this->class_id)."'>".$display."</a>";
		}else{
			$url = akimbo_crm_class_permalink($this->class_id);
		}
		return $url;
	}

	function related_classes(){//used for swap student button
		global $wpdb;
		$age = $this->class_info->age_slug;
		$classes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE age_slug = '$age' ORDER BY session_date ASC" );
		return $classes;
	}

	function enrolment_related_classes($format = 'all', $date = NULL){//all, ids
		global $wpdb;
		$variation = $this->class_info->class_id;
		$semester = $this->class_info->semester_slug;
		$title = $this->class_info->class_title;//check class title so this work for casual classes too
		$result = array();
		if($semester != NULL){
			if($date != NULL){//classes from specific  e.g. future
				$classes = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation' 
					AND semester_slug = '$semester' 
					AND class_title = '$title' 
					AND session_date >= '$date' 
					ORDER BY session_date ASC" );
			}else{
				$classes = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_id = '$variation' 
					AND semester_slug = '$semester' 
					AND class_title = '$title' 
					ORDER BY session_date ASC" );
			}
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

	function class_semester(){
		global $wpdb;
		$date = $this->class_info->session_date;
		$semester = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_semesters WHERE semester_start <= '$date' AND semester_end >= '$date' LIMIT 1");
		
		return $semester->semester_slug;
	}

	/**
	 * 
	 * move to class functions, send info
	 * 
	 */
	function matched_orders(){
		global $wpdb;
		$variation_id = $this->class_info->class_id;
		$items_ids = get_all_orders_items_from_a_product_variation( $this->class_info->class_id );
		$matched_orders = 0;
		foreach($items_ids as $item_id){
			$item_info = crm_get_item_available_passes($item_id);
			if($item_info['available']){
				$matched_orders++;
				if($matched_orders == 1){
					echo "<table width='80%''><tr><th colspan='2'>Matched Orders</th></tr>";
					$table = true;
				}
				$user = new Akimbo_Crm_User($item_info['user_id']);
							
				echo "<tr><td>Order ".$item_info['order_id'].", ". wc_get_order_item_meta( $item_id, 'class-time', true );
				echo "<br/><i>Booked by " . $user->get_firstname.", ".$item_info['remaining']." remaining</i></td><td>";

				/**
				 * Assign order to correct student
				 */
				?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<?php echo "Student: ".$user->student_dropdown(); ?>
				<input type="hidden" name="customer_id" value="<?php echo $user_id; ?>">
				<input type="hidden" name="item_id" value="<?php echo $item_id;?>">
				<input type="hidden" name="class_id" value="<?php echo $this->class_id;?>">
				 Start Date: <input type="date" name="start_date">
				<input type="hidden" name="action" value="kids_enrolment_confirmation">
				<input type='submit' value='Enrol'> </form>
				<!--Add new student button-->
				<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<input type="hidden" name="customer_id" value="<?php echo $user_id; ?>">
				<input type="hidden" name="class" value="<?php echo $this->class_id;?>">
				<input type="hidden" name="action" value="admin_add_new_student">
				or <input type="text" name="student" placeholder="Student first name"> <input type='submit' value='Add New'>
				</form></tr>
				<?php 
			}
		}
		if(isset($table)){echo "</table";}//close table tag if matched orders table created
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
}