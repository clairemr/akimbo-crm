<?php
echo "<h2>Manage Invoices</h2>";

//Unpaid invoices
echo "<table>";
echo "<tr><th>Date</th><th>Invoice number</th><th>Trainer</th><th>Amount</th><th>Crosscheck</th><th></th></tr>";
$unpaid_invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_paid <= '2001-00-00' ");
$total = 0;
if($unpaid_invoices){
	foreach($unpaid_invoices as $unpaid_invoice){
		$user_id = $unpaid_invoice->trinv_trainer;
		$user_info = get_userdata($user_id);
		echo "<tr><td>".$unpaid_invoice->trinv_date."</td><td>".$unpaid_invoice->trinv_no."</td><td>$user_info->first_name</td><td>".$unpaid_invoice->trinv_amount."</td><td>";
		$total = $total += $unpaid_invoice->trinv_amount;
		$site = get_site_url()."/wp-admin/admin.php?page=akimbo-crm&tab=invoices&check=".$unpaid_invoice->trinv_no;
		echo "<a href='".$site."'>Check</a></td><td>";
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input type="hidden" name="invoice" value="<?php echo $unpaid_invoice->trinv_no; ?>">
		<input type="hidden" name="action" value="crm_payroll_invoices_paid">
		<input type="submit" value="Mark as Paid">
		</form>
		<?php
		echo "</td></tr>";
		$check = $_GET['check'];
		$trinv = $unpaid_invoice->trinv_no;
		if($trinv == $check){
			
			$class_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_class_list WHERE trainer_inv = '$check' OR trainer2_inv = '$check'");
			//echo $class_count;
			
			
			echo "<tr><td></td><td>Classes x".$class_count." </td></tr>";
			$details = $wpdb->get_results("SELECT book_ref FROM {$wpdb->prefix}crm_bookings WHERE book_trinv = '$check' OR book_tr2inv = '$check'");
			foreach($details as $detail){echo "<tr><td></td><td>".$detail->book_ref."</td></tr>";}
			
		}
		
		$unpaid_invs_array[] = $unpaid_invoice->trinv_no;
	}
	echo "</table>";
}
echo "Total = ".$total;

echo "<br/><b>Add Invoice</b>";
echo "<table>";
?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<tr><td><input name= "trinv_no" type="text" placeholder="Add invoice"></td>
	<td><select name="trainer"><option value="0"><i>Trainer name</i></option>
		<?php crm_trainer_dropdown_list(); ?><option value="247"><i>Briony Hadfield</i></option></select></td>
	<td><input name= "amount" type="text" placeholder="Amount"></td>
	<td><input type="submit" value="Add"></td></tr>
	<input type="hidden" name="action" value="crm_payroll_add_invoices">
	</form><?php
echo "</table>";
//end unpaid invoices


echo "<h2>Assign Classes:</h2>";
$invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE session_date <= '$today' AND trainer_inv = '' OR session_date <= '$today' AND trainer2_inv = '' ORDER BY session_date ASC");
if($invoices){echo "<table><tr><th>Trainer</th><th>Class</th><th>Add Or Select Invoice Number</th><th></th></tr>";$est=0;}
else {echo "All paid!";}
foreach ($invoices as $invoice){
	if(!$invoice->trainer_id){//don't show if no one took the class
	} elseif($invoice->trainer_id <= 2){//don't show me
	} elseif($invoice->trainer_inv){ //don't show paid invoices
	} else{
		$user_id = $invoice->trainer_id;
		$user_info = get_userdata($user_id);
		echo "<tr><td>".$user_id.". ".$user_info->first_name."</td><td>".$invoice->class_title.", ".$invoice->session_date."</td><td>";
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input name= "newinvoice" type="text" value="<?php echo $invoice->trainer_inv; ?>"> <select name="selectinvoice">
		<?php //show pre-existing invoice numbers
			$invs = $wpdb->get_col("SELECT trinv_no FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_trainer = '$user_id' AND trinv_paid <= '2001-00-00' ORDER BY trinv_no ASC");
			foreach ($invs as $inv){echo '<option value="' .$inv .'">' .$inv .'</option>';}
			?>
		</select> <td><input type="submit" value="Assign to invoice"></td>
		<input type="hidden" name="class" value="<?php echo $invoice->list_id; ?>">
		<input type="hidden" name="trainer1" value="<?php echo $invoice->trainer_id; //trainer 2 for next section ?>">
		<input type="hidden" name="action" value="crm_payroll_class_invoices">
		</form><?php
		echo "</td></tr>";
		$est++;
	}
	if(!$invoice->trainer2_id){//don't show if no one took the class
	} elseif($invoice->trainer2_id <= 2){//don't show me
	} elseif($invoice->trainer2_inv){ //don't show paid invoices
	} else{
		$user_id = $invoice->trainer2_id;
		$user_info = get_userdata($user_id);
		echo "<tr><td>".$user_id.". ".$user_info->first_name."</td><td>".$invoice->class_title.", ".$invoice->session_date."</td><td>";
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input name= "newinvoice" type="text" value="<?php echo $invoice->trainer_inv; ?>"> <select name="selectinvoice">
		<?php //show pre-existing invoice numbers
			$invs = $wpdb->get_col("SELECT trinv_no FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_trainer = '$user_id' AND trinv_paid <= '2001-00-00' ORDER BY trinv_no ASC");
			foreach ($invs as $inv){echo '<option value="' .$inv .'">' .$inv .'</option>';}
			?> <td><input type="submit" value="Assign to invoice"></td>
		<input type="hidden" name="class" value="<?php echo $invoice->list_id; ?>">
		<input type="hidden" name="trainer2" value="<?php echo $invoice->trainer2_id; //trainer 2 for next section ?>">
		<input type="hidden" name="action" value="crm_payroll_class_invoices">
		</form><?php
		echo "</td></tr>";
		$est++;
	}
}
echo "</table>";
$cost = $est*30;
echo "<br/>Cost estimate: ".$cost;
echo "<br/><b>Parties & Bookings:</b>";
$invoices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}crm_bookings WHERE book_trinv = '' OR book_tr2inv = '' ORDER BY book_ref ASC");
if($invoices){echo "<table><tr><th>Trainer</th><th>Booking Reference</th><th>Add Or Select Invoice Number</th><th></th></tr>";}
else {echo "All paid!";}
foreach ($invoices as $invoice){
	if(!$invoice->book_trainer_id){//don't show if no one took the class
	} elseif($invoice->book_trainer_id <= 2){//don't show me
	} elseif($invoice->book_trinv){ //don't show paid invoices
	} else{
		$user_id = $invoice->book_trainer_id;
		$user_info = get_userdata($user_id);
		echo "<tr><td>".$user_id.". ".$user_info->first_name."</td><td>".$invoice->book_ref."</td><td>";
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input name= "newinvoice" type="text" value="<?php echo $invoice->book_trinv; ?>"> <select name="selectinvoice">
		<?php //show pre-existing invoice numbers
			$invs = $wpdb->get_col("SELECT trinv_no FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_trainer = '$user_id'  AND trinv_paid <= '2001-00-00' ORDER BY trinv_no ASC");
			foreach ($invs as $inv){echo '<option value="' .$inv .'">' .$inv .'</option>';}
			?>
		</select> <td><input type="submit" value="Assign to invoice"></td>
		<input type="hidden" name="booking" value="<?php echo $invoice->book_id; ?>">
		<input type="hidden" name="trainer1" value="<?php echo $invoice->book_trainer_id; //trainer 2 for next section ?>">
		<input type="hidden" name="action" value="crm_payroll_class_invoices">
		</form><?php
		echo $invoice->book_id.$invoice->book_trainer_id."</td></tr>";
	}
	
	//trainer 2
	if(!$invoice->book_trainer2_id){//don't show if no one took the class
	} elseif($invoice->book_trainer2_id <= 2){//don't show me
	} elseif($invoice->book_tr2inv){ //don't show paid invoices
	} else{
		$user_id = $invoice->book_trainer2_id;
		$user_info = get_userdata($user_id);
		echo "<tr><td>".$user_id.". ".$user_info->first_name."</td><td>".$invoice->book_ref."</td><td>";
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
		<input name= "newinvoice" type="text" value="<?php echo $invoice->book_tr2inv; ?>"> <select name="selectinvoice">
		<?php //show pre-existing invoice numbers
			$invs = $wpdb->get_col("SELECT trinv_no FROM {$wpdb->prefix}crm_trainer_invoices WHERE trinv_trainer = '$user_id'  AND trinv_paid <= '2001-00-00' ORDER BY trinv_no ASC");
			foreach ($invs as $inv){echo '<option value="' .$inv .'">' .$inv .'</option>';}
			?>
		</select> <td><input type="submit" value="Assign to invoice"></td>
		<input type="hidden" name="booking" value="<?php echo $invoice->book_id; ?>">
		<input type="hidden" name="trainer2" value="<?php echo $invoice->book_trainer2_id; //trainer 2 for next section ?>">
		<input type="hidden" name="action" value="crm_payroll_class_invoices">
		</form><?php
		echo "</td></tr>";
	}
}
echo "</table>";

	//unrecorded invoices
	$results = array_diff($invs_array, $unpaid_invs_array);//in the first array but not the second
	echo "Please review the following invoices";
	echo "<table>";
	//trinv_no, trainer, amount
	
	foreach($results as $result){
		?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php
		echo "<tr><td>".$result."</td>";?>
		<input name= "trinv_no" type="hidden" value="<?php echo $result; ?>">
		<td><input name= "trainer" type="text" placeholder="Trainer"></td>
		<td><input name= "amount" type="text" placeholder="Amount"></td>
		<input type="hidden" name="action" value="crm_payroll_add_invoices">
		<td><input type="submit" value="Add"></td><?php 
		echo "</td></tr>";
		?></form><?php
	}
	echo "</table>";
	//end unrecorded invoices