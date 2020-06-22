<?php
global $wpdb;

/*************************************************************************************************************
*
* Upcoming bookings
*
**************************************************************************************************************/
if($active_tab == 'bookings'){
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
	$crm_date = crm_date_setter_month($date);
	$orders = get_all_bookings_for_date_range($crm_date['start_time']);
	$bookings = array();
	foreach($orders as $order){$bookings[] = new Akimbo_Crm_Booking($order->avail_id);}
	//crm_available_booking_dates_dropdown($product_id);	

	$header = "<h2><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=bookings&date=".$crm_date['previous_month']."'><input type='submit' value='<'></a> Bookings: ".$crm_date['month']." <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=bookings&date=".$crm_date['next_month']."'><input type='submit' value='>'></a></h2>";
	echo apply_filters('akimbo_crm_manage_bookings_header', $header);
	apply_filters('akimbo_crm_manage_bookings_before_calendar', crm_date_selector("akimbo-crm4"));

	echo "<table width='95%'><tr>";
	$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
	for($x = 1; $x <= 7; $x++){
		echo "<th width = '13%'>".$days[$x-1]."</th>";
		if($days[$x-1] == $crm_date['first_day']){$start = $x;}
	}
	echo "</tr>";
	$rows = 5;
	$day_of_the_month = 1;
	$count_started = 0;
	for($x = 1; $x <= $rows; $x++){
		echo "<tr>";
		for($i = 1; $i <= 7; $i++){
			echo "<td>";
			if($i == $start){$count_started = 1;}
			if($count_started >= 1 && $day_of_the_month <= $crm_date['number_of_days']){
				echo $day_of_the_month."<br/>";
				$select_value = date("Y-m-$day_of_the_month", strtotime($date));
				foreach($bookings as $booking){
					//var_dump($booking);
					$booking_date = $booking->get_booking_date('Y-m-j');
					if($select_value == $booking_date){
						echo "<b>".$booking->product_name().", </b>".$booking->get_booking_date('G:ia');
						echo "<br/><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details&booking=".$booking->avail_id."'>View Details</a><br/>";
						echo $booking->trainer_names();
					}
				}
				$day_of_the_month++;
			}
			echo "</td>";
		}
		echo "</tr>";
		if($x == 5 && $day_of_the_month <= $crm_date['number_of_days']){
			$rows = 6;
		}
	}
	echo "</table>";
}

if($active_tab == 'booking_details'){
	if(isset($_GET['message'])){
		$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Updates successful!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
		echo apply_filters('manage_booking_details_update_notice', $message);
	}
	$date = current_time('Y-m-d');
	$crm_date = crm_date_setter_month($date);
	
	if(!isset($_GET['booking'])){
		?><form action="admin.php" method="get">
		<input type="hidden" name="page" value="akimbo-crm4" /><input type="hidden" name="tab" value="booking_details" />
		Please select an upcoming booking: <select name="booking"><?php
			get_all_bookings_for_date_range($crm_date['start_time'], "dropdown");//or replace start with $date for future bookings only
		?></select><input type="submit" value="Select"></form> 
		<form action="admin.php" method="get">or search orders: 
		<input type="hidden" name="page" value="akimbo-crm4" /><input type="hidden" name="tab" value="booking_details" />
		<input type="number" name="order"> <input type="submit" value="View"></form><?php
	}elseif(isset($_GET['order'])){
		echo "Haven't yet finished this function, but will eventually let you add booking dates to orders that don't have one set";
	}else{ 
	    //if(get_post_type($_GET['order']) != "shop_order"){echo "Invalid order number<br/>";}
	        $booking = new Akimbo_Crm_Booking($_GET['booking']);
			echo "<h2>Booking details: ".$booking->get_booking_date()."</h2>";
			crm_update_trainer_dropdown("booking", $booking->avail_id, $booking->get_trainers(), $booking->get_availabilities());
			echo apply_filters('akimbo_crm_manage_bookings_detailed_info', $booking->get_booking_info());
			
			
			echo "<br/><hr><h3><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=booking_details'><button>Reset</button></a></h3>";

			echo "<br/><hr><h2>Archived Functions: </h2>";
			echo apply_filters('akimbo_crm_manage_bookings_tasks', $booking->tasks());
			echo "<br/><button>Edit slot time</button>";//update date in availability table, so slot doesn't reappear for booking
			//var_dump($booking);		
	}
	crm_roster_edit_button();
}


/*************************************************************************************************************
*
* Availabilities
*
**************************************************************************************************************/
if($active_tab == 'calendar'){        
	$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d 0:00');//ternary operator
	$crm_date = crm_date_setter_month($date);
	$availability = crm_check_booking_availability(0, $crm_date['start_time'], $crm_date['end']);
	$header = "<h2><a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=calendar&date=".$crm_date['previous_month']."'><input type='submit' value='<'></a> ".$crm_date['month']." <a href='".get_site_url()."/wp-admin/admin.php?page=akimbo-crm4&tab=calendar&date=".$crm_date['next_month']."'><input type='submit' value='>'></a></h2>";
	//echo $crm_date['previous_month'].$crm_date['next_month'];
	echo apply_filters('akimbo_crm_manage_availabilities_header', $header);
	apply_filters('akimbo_crm_manage_availabilities_before_calendar', crm_date_selector("akimbo-crm4", "calendar"));
	if (current_user_can('manage_woocommerce')){//only admin can view & edit availabilities
		echo "<table border='1' width='95%'><tr>";
		$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
		for($x = 1; $x <= 7; $x++){
			echo "<td width = '13%'>".$days[$x-1]."</td>";
			if($days[$x-1] == $crm_date['first_day']){$start = $x;}
		}
		echo "</tr>";
		$rows = 5;
		$day_of_the_month = 1;
		for($x = 1; $x <= $rows; $x++){
			echo "<tr>";
			for($i = 1; $i <= 7; $i++){
				echo "<td>";
				if($i == $start){$count_started = 1;}
				if($count_started && $day_of_the_month <= $crm_date['number_of_days']){
					echo $day_of_the_month."<br/>";
					$select_value = date("Y-m-$day_of_the_month", strtotime($date));
					if(isset($availability)){
						foreach($availability as $avail_id){
							$avail = new Akimbo_Crm_Availability($avail_id->avail_id);
							$avail_date = $avail->get_booking_date("Y-m-j");
							if($avail_date == $select_value){
								echo "Available: ".$avail->get_booking_date("g:ia");
								crm_simple_delete_button('crm_availability', "avail_id", "$avail_id->avail_id", "/wp-admin/admin.php?page=akimbo-crm4&tab=calendar");
								$avail->book_button();
								echo "<hr>";
							}
						}
					}
					$day_of_the_month++;
				}
				echo "</td>";
			}
			echo "</tr>";
			if($x == 5 && $day_of_the_month <= $crm_date['number_of_days']){$rows = 6;}
		}
		echo "</table>";
		crm_add_booking_availability();
		echo "<br/>Availabilities: ";
		echo crm_available_booking_dates_dropdown();
	}
	
}

if($active_tab == 'partydata'){ include 'includes/party_data.php'; }