<?php
global $wpdb;

$date = (isset($_GET['date'])) ? $_GET['date'] : current_time('Y-m-d');//ternary operator
$crm_date = crm_date_setter_week($date);
$payment_date = date("Y-m-d-h:ia", strtotime($crm_date['week_start']));
$last_payment = date("Y-m-d-h:ia", strtotime($crm_date['week_end']));

echo "<h4>Payslip period: ".date("l jS M", strtotime($payment_date))." - ".date("l jS M", strtotime($last_payment));
	echo "<br/>Payment date: ".date("l jS M", strtotime('thursday this week', strtotime($date)))."</h4>";

crm_date_selector("akimbo-crm3", "payroll");

$total =0;
$userid = get_current_user_id();
$user = get_userdata( $userid );
$role = implode(', ', $user->roles);
setlocale(LC_MONETARY, 'en_US.UTF-8');

/*$trainer_meta = get_user_meta($userid);
//In order to access the data in this example you need to dereference the array that is returned for each key e.g. $user_meta['employee_level'][0]
echo "<br/>User level: ".$trainer_meta['employee_level'][0];
$level = $trainer_meta['employee_level'][0];
$type = $trainer_meta['employee_type'][0];
$rate = $wpdb->get_var("SELECT rate FROM {$wpdb->prefix}crm_award_rates WHERE level = '$level'");
if($type == "casual"){
	$weekday_rate = $rate * 1.25;
	$weekend_rate = $rate * 1.3;
} else{
	$weekday_rate = $rate;
	$weekend_rate = $rate;
}
echo "<br/>Weekday rate (".$type."): $".round($weekday_rate, 2);
echo "<br/>Weekend rate (".$type."): $".round($weekend_rate, 2);*/

$staff = array('shop_manager', 'author', 'administrator');
if ($role == "administrator") {//Admin view
   echo "<table>";
	$args = array('role__in' => array('shop_manager', 'author', 'administrator'),);
	$users = get_users( $args );
	foreach ($users as $user) {
		$trainer = $user->ID;
		$subtotal = 0;
		$invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE trainer_id = '$trainer' AND session_date >= '$last_payment' AND session_date <= '$payment_date' OR trainer2_id = '$trainer' AND session_date >= '$last_payment' AND session_date <= '$payment_date' ORDER BY session_date ASC");
		$rosters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_roster WHERE trainer_id = '$trainer' AND start_time >= '$last_payment' AND start_time <= '$payment_date' ORDER BY start_time ASC");
		if(!$invoices && !$rosters){}else{
			echo "<tr bgcolor = '#33ccff'><td width='50%'><strong>".$user->display_name."</strong></td><td>Duration</td><td>Rate</td><td>Payrun</td></tr>";
			
			$trainer_meta = get_user_meta($trainer);
			//In order to access the data in this example you need to dereference the array that is returned for each key e.g. $user_meta['employee_level'][0]
			$level = $trainer_meta['employee_level'][0];
			$type = $trainer_meta['employee_type'][0];
			$rate = $wpdb->get_var("SELECT rate FROM {$wpdb->prefix}crm_award_rates WHERE level = '$level'");
			if($type == "casual"){
				$weekday_rate = $rate * 1.25;
				$weekend_rate = $rate * 1.3;
			} else{
				$weekday_rate = $rate;
				$weekend_rate = $rate;
			}
			
			foreach($invoices as $invoice){
				$duration = $invoice->duration/60;
				echo "<tr><td align='right'>".$invoice->class_title.", ".date('l jS M', strtotime($invoice->session_date))."</td><td>".$invoice->duration." mins</td><td>";
				$day = date("D", strtotime($invoice->session_date));
				
				
				
				
				if(!empty($invoice->trainer_inv) || !empty($invoice->trainer2_inv)){
					echo "PAID</td></tr>";
				} else{
					if($day == 'Sat' || $day == 'Sun'){
						$pay = $weekend_rate * $duration;
					} else {
						$pay = $weekday_rate * $duration;
					}
					
					echo "$".round($pay, 2). "</td><td><i>Payrun</i></td></tr>";
					$subtotal += $pay;
				}
			}
			

			foreach($rosters as $roster){
				$duration = $roster->duration/60;
				echo "<tr><td align='right'>".ucfirst($roster->shift_type).", ".date('h:ia l jS M', strtotime($roster->start_time))."</td><td>".$roster->duration." mins</td><td>";
				$day = date("D", strtotime($roster->start_time));
				if($day == 'Sat' || $day == 'Sun'){
					$pay = $weekend_rate * $duration;
				} else {
					$pay = $weekday_rate * $duration;
				}
				echo "$".round($pay, 2). "</td><td><i>Payrun</i></td></tr>";
				$subtotal += $pay;
			}
			
			$super = $subtotal * 0.095;
			
			
			
			//Medicare levy included in scales. Only need to update if someone claims an exemption
			
			
			$tax_threshold = $trainer_meta['employee_threshold'][0];//tax free threshold
			$tax_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_tax_table WHERE earnings >= $subtotal AND threshold = '$tax_threshold' ORDER BY earnings ASC LIMIT 1");
			$taxA = $tax_info->a;
			$taxB = $tax_info->b;
			$taxPay = round($subtotal);
			$PAYG = $taxA * (round($subtotal) + 0.99) - $taxB;
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>Subtotal: </i></td><td><i>$".$subtotal."</i></td></tr>";
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>PAYG: </i></td><td><i>-$".$PAYG."</i></td></tr>";
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>Superannuation: </i></td><td><i>$".round($super, 2)." (not included in total)</i></td></tr>";
			$trainer_total = $subtotal-$PAYG;
			echo "<tr bgcolor = '#66eeff'><td colspan='2'>";
			
			
			//echo "<button>Email pay slip</button>";
			
			?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
			<?php 
			
			$userdata = get_userdata($trainer);
        		$to = $userdata->user_email;
			
			?>
			<input type="hidden" name="date" value="<?php echo $payment_date; ?>">
			<input type="hidden" name="email" value=" <?php echo $to; ?> ">
			<input type="hidden" name="action" value="crm_email_trainer_payslip">
			<input type="submit" value="Email Payslip">
			</form>
			<?php 
			echo "</td><th>$".round($trainer_total, 2)."</th></tr>";
		
			$payrun_id = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_payroll ORDER BY payrun_id DESC LIMIT 1");
			//add conditional statement to hide this if info has been recorded
			//$paydate = $payrun_id->date;
			$paid = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_payroll WHERE trainer = '$trainer' AND date >= '$payment_date'");
			//var_dump($paid);
			//echo $paid;
			if(!$paid){
				?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
				<tr><td colspan='2'>Payrun Id: <input type="text" name="payrun_id" value="<?php echo $payrun_id->payrun_id; ?>"></td><td>
				<input type="hidden" name="action" value="crm_add_new_payslip_data">
				<input type="hidden" name="date" value="<?php echo $payment_date; ?>">
				<input type="hidden" name="trainer" value="<?php echo $trainer;?>">
				<input type="hidden" name="wage" value="<?php echo round($subtotal, 2);?>">
				<input type="hidden" name="payg" value="<?php echo round($PAYG, 2);?>">
				<input type="hidden" name="super" value="<?php echo round($super, 2);?>">
				<input type="submit" value="Record Payment">
				</form></td></tr><?php 
			}
		
		}
		if($trainer != 2){//don't count Claire
			$total += $subtotal;
			$payrun_total += $trainer_total;
		}
		
		
	}

	echo "</table>";
	echo "<br/>Payrun total = ".$total;
	echo "<br/>Payrun total minus payg = ".$payrun_total;
	echo "<br/><b>Email payslip button should also mark it as paid by inserting a row into the invoices table. Use payroll date in place of invoice number in class table. Invoice number = 'payroll' in invoice table</b><br/>";
   
} else{//Single staff member   
//} elseif(in_array($role, $staff)){//Single staff member
	echo "<table>";
	$subtotal = 0;
	$invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE trainer_id = '$userid' AND session_date >= '$last_payment' AND session_date <= '$payment_date' OR trainer2_id = '$userid' AND session_date >= '$last_payment' AND session_date <= '$payment_date' ORDER BY session_date ASC");
	$rosters = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_roster WHERE trainer_id = '$userid' AND start_time >= '$last_payment' AND start_time <= '$payment_date' ORDER BY start_time ASC");
		if(!$invoices && !$rosters){}else{
			echo "<tr bgcolor = '#33ccff'><td width='50%'><strong>".$user->display_name."</strong></td><td>Duration</td><td>Rate</td></tr>";
			
			$trainer_meta = get_user_meta($userid);
			//In order to access the data in this example you need to dereference the array that is returned for each key e.g. $user_meta['employee_level'][0]
			$level = $trainer_meta['employee_level'][0];
			$type = $trainer_meta['employee_type'][0];
			$rate = $wpdb->get_var("SELECT rate FROM {$wpdb->prefix}crm_award_rates WHERE level = '$level'");
			if($type == "casual"){
				$weekday_rate = $rate * 1.25;
				$weekend_rate = $rate * 1.3;
			} else{
				$weekday_rate = $rate;
				$weekend_rate = $rate;
			}
			echo "<br/>Weekday rate (".$type."): $".round($weekday_rate, 2);
			echo "<br/>Weekend rate (".$type."): $".round($weekend_rate, 2);
			echo "<br/>Employee level ".$level.", ".$type;
			
			
			

			foreach($invoices as $invoice){
				$duration = $invoice->duration/60;
				echo "<tr><td align='right'>".$invoice->class_title.", ".date('l jS M', strtotime($invoice->session_date))."</td><td>".$invoice->duration." mins</td><td>";
				$day = date("D", strtotime($invoice->session_date));
				
				
				
				
				if(!empty($invoice->trainer_inv) || !empty($invoice->trainer2_inv)){
					echo "PAID</td></tr>";
				} else{
					if($day == 'Sat' || $day == 'Sun'){
						$pay = $weekend_rate * $duration;
					} else {
						$pay = $weekday_rate * $duration;
					}
					
					echo "$".round($pay, 2). "</td><td><i>Payrun</i></td></tr>";
					$subtotal += $pay;
				}
			}
			echo "<hr>";

			foreach($rosters as $roster){
				$duration = $roster->duration/60;
				echo "<tr><td align='right'>".ucfirst($roster->shift_type).", ".date('h:ia l jS M', strtotime($roster->start_time))."</td><td>".$roster->duration." mins</td><td>";
				$day = date("D", strtotime($roster->start_time));
				if($day == 'Sat' || $day == 'Sun'){
					$pay = $weekend_rate * $duration;
				} else {
					$pay = $weekday_rate * $duration;
				}
				echo "$".round($pay, 2). "</td><td><i>Payrun</i></td></tr>";
				$subtotal += $pay;
			}
			
			$super = $subtotal * 0.095;
			
			
			
			//Medicare levy included in scales. Only need to update if someone claims an exemption
			
			
			$tax_threshold = $trainer_meta['employee_threshold'][0];//tax free threshold
			$tax_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_tax_table WHERE earnings >= $subtotal AND threshold = '$tax_threshold' ORDER BY earnings ASC LIMIT 1");
			$taxA = $tax_info->a;
			$taxB = $tax_info->b;
			$taxPay = round($subtotal);
			$PAYG = $taxA * (round($subtotal) + 0.99) - $taxB;
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>Subtotal: </i></td><td><i>$".$subtotal."</i></td></tr>";
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>PAYG: </i></td><td><i>-$".$PAYG."</i></td></tr>";
			echo "<tr bgcolor = '#fff'><td colspan='2' align='right'><i>Superannuation: </i></td><td><i>$".round($super, 2)." (not included in total)</i></td></tr>";
			$trainer_total = $subtotal-$PAYG;
			echo "<tr bgcolor = '#66eeff'><td colspan='2'><button>Email pay slip</button></td><th>$".round($trainer_total, 2)."</th></tr>";
		}


	echo "</table>";
}


export_payroll_csv_file();
//https://keypay.com.au/timesheets-bulk-submission
echo "<br/><br/><a href='https://quickbooks.intuit.com/au/quickbooks-login/' target='_blank'><button>Log In to Quickbooks</button></a>";

echo "<h2>EOFY totals: </h2>";
$trainer = 2;
$PAYG = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}crm_payroll WHERE trainer = '$trainer' AND type = 'PAYG' AND date <= '2019-06-30'");
$wage = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}crm_payroll WHERE trainer = '$trainer' AND type = 'wage' AND date <= '2019-06-30'");
$super = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}crm_payroll WHERE trainer = '$trainer' AND type = 'super' AND date <= '2019-06-30'");
$invoices = $wpdb->get_var("SELECT SUM(trinv_amount) FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_trainer = '$trainer' AND trinv_paid <= '2019-07-01' AND trinv_paid >= '2018-07-01'");
echo "<br/>PAYG: ".$PAYG;
echo "<br/>Wages: ".$wage;
echo "<br/>Super: ".$super;
echo "<br/>Invoices: ".$invoices;
