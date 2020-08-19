<?php 

add_action( 'admin_post_add_new_schedule', 'add_new_schedule' );

add_action( 'admin_post_crm_add_new_semester', 'crm_add_new_semester' );

/*
*
* Manage Schedules page
*
*/
function akimbo_crm_manage_schedules(){
	global $wpdb;
	$capability = 'manage_options';
	if (current_user_can($capability)){
		if(isset($_GET['message'])){
			$message = ($_GET['message'] == "success") ? "<div class='updated notice is-dismissible'><p>Schedule added!</p></div>" : "<div class='error notice is-dismissible'><p>Update failed, please try again</p></div>";
			echo apply_filters('manage_classes_schedule_update_notice', $message);
		}
		if(isset($_GET['class_id'])){
			$class = new Akimbo_Crm_Class($_GET['class_id']);
			echo "<h2>Edit Class: ".$class->get_the_title()."</h2>";
			echo "<strong>Age: </strong>".$class->age_slug()."<br/><strong>Type: </strong>".$class->get_class_type()."<br/><strong>Semester: </strong>".$class->class_semester()."<br/>";
			crm_update_class_date_form($class);
			echo "<hr>";	
		}
		echo "<h2>Semesters</h2>";
		display_semesters("future");
		echo apply_filters('manage_classes_add_semester_button', crm_add_new_semester_button());

		echo "<br/><hr><h2>Add New Class Schedule</h2>";
		crm_add_new_class_schedule();
		echo "<br/><hr><h2>Confirm Enrolments</h2>";
		$posts = crm_get_posts_by_type("enrolment", "return", NULL);
		foreach($posts as $product){
			$args = array(
				'post_type'     => 'product_variation',
				'post_status'   => array( 'private', 'publish' ),
				'numberposts'   => -1,
				'orderby'       => 'menu_order',
				'order'         => 'asc',
				'post_parent'   => $product->ID // get parent post-ID
			);
			$variations = get_posts( $args );
			if($variations != NULL){//add these details in separate function
				foreach($variations as $variation){
					$title = $variation->post_title.", ".$variation->post_excerpt;//use excerpt to get Class Time
					crm_confirm_class_schedule($variation->ID, $title, $product->ID);
				}
			}else{
				crm_confirm_class_schedule($product->ID, $product->post_title);
			}	
		}
		
		
		
		//https://stackoverflow.com/questions/47518280/create-programmatically-a-woocommerce-product-variation-with-new-attribute-value <-- add new variation		
	}else{echo "<br/>Sorry, you don't have permission to edit schedules";}
		crm_calculate_pro_rata_price();
}

function crm_confirm_class_schedule($class_id, $title = NULL, $product_id = NULL){
	global $wpdb;
	$today = current_time('Y-m-d');
	$added = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE session_date >= '$today' AND class_id = '$class_id' ORDER BY session_date ASC LIMIT 1");
	if($added){
		echo $title.". Next class: ".date("g:ia l jS F", strtotime($added->session_date))." <a href='".akimbo_crm_permalinks("classes", "link", NULL, array('class' => $added->list_id))."'>View Class</a> <br/><hr>";//for testing purposes, probably hide in final function
	}else{
		echo $class_id.": ".$title;
		$trainer1 = get_post_meta($class_id, 'trainer1', true );
		if($trainer1 >= 1){
			echo "<br/>Trainers: ";
			echo crm_user_name_from_id($trainer1);
			$trainer2 = get_post_meta($class_id, 'trainer2', true );
			if($trainer2 >= 1){
				echo " and ".crm_user_name_from_id($trainer2);
			}
			$confirm = true;
		}else{
			echo "<br/>Trainers not set";
			$confirm = false;
		}
		$start_time = get_post_meta($class_id, 'start_time', true );
		if(!isset($start_time)){$confirm = false;}
		if($confirm == true){
			$product_id = ($product_id == NULL) ? $class_id : $product_id;
			$duration = get_post_meta($product_id, 'duration', true );
			?><br/><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
			Start: <input type="date" name="new_class_start"><?php crm_semester_dropdown_select("semester", "future"); ?>
			<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
			<input type="hidden" name="class_name" value="<?php echo $title; ?>">
			<input type="hidden" name="duration" value="<?php echo $duration; ?>">
			<input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
			<input type="hidden" name="new_class_time" value="<?php echo $start_time; ?>">
			<input type="hidden" name="trainer1" value="<?php echo $trainer1; ?>">
			<input type="hidden" name="trainer2" value="<?php echo $trainer2; ?>">
			<input type="hidden" name="action" value="add_new_schedule">
			<?php if(isset($url)){echo "<input type='hidden' name='url' value='".$url."'>"; } ?>
			<input type='submit' value='Confirm Class Start'></form><?php
		}else{
			echo "<br/><b>Class details not set: </b>";
		}
		crm_admin_order_link($product_id, "Edit product details");
		echo "<br/><hr><br/>";
	}
}

/*
* 
* Update product: add meta data and allow scheduling
*
*/
add_action( 'add_meta_boxes', 'product_details_add' );  //add class details meta box
//product_details_call //information in meta box
add_action( 'save_post', 'product_details_save' );      //save meta data      
add_action( 'woocommerce_product_after_variable_attributes', 'variation_settings_fields', 10, 3 );  // Add Variation Settings      
add_action( 'woocommerce_save_product_variation', 'save_variation_settings_fields', 10, 2 );    // Save Variation Settings

function product_details_add() {
    add_meta_box( 'class_details', 'Class Details', 'product_details_call', 'product', 'normal', 'high' );
}

function product_details_call( $post ) {
	$details = get_post_meta(get_the_ID());
	//Enable Akimbo CRM
	echo "<input type='checkbox' name='is_bookable' id='is_bookable'";
	if(isset($details['is_bookable'])){echo "checked";}
	echo " >Enable Akimbo CRM. ";//use for all products in CRM
	if(isset($details['is_bookable'])){
		// Casual Class product
		echo "<br/><input type='checkbox' name='is_casual' id='is_casual'";
		if(isset($details['is_casual'])){echo "checked";}
		echo " >Casual Class. ";
		// Private booking
		echo "<br/><input type='checkbox' name='is_booking' id='is_booking'";
		if(isset($details['is_booking'])){echo "checked";}
		echo " >Private booking. ";

		/**
		 * Get variation information
		 */
		$product_id = get_the_ID();
		$args = array(
		    'post_type'     => 'product_variation',
		    'post_status'   => array( 'private', 'publish' ),
		    'numberposts'   => -1,
		    'orderby'       => 'menu_order',
		    'order'         => 'asc',
		    'post_parent'   => $product_id // get parent post-ID
		);
		$variations = get_posts( $args );
		//Set age slug for all CRM products
		$age_slug = (isset($details['age_slug'])) ? $details['age_slug'][0] : "";
		echo "<br/>Age: <select name='age_slug'>";
		if(isset($details['age_slug'])){
			echo "<option value='".$details['age_slug'][0]."'>".ucwords($details['age_slug'][0])."</option><option>***</option>";
		} 
		echo "<option value='kids'>Kids</option>
			<option value='adult'>Adult</option>
			<option value='playgroup'>Playgroup</option>
			<option value='private'>Private</option>
			</select>";

		/**
		 * Classes
		 */
		if(!isset($details['is_booking'])){
			$duration = (isset($details['duration'])) ? $details['duration'][0] : 0;
			echo "<br/>Class length: <input type='number' value='".$duration."' name='duration' id='duration'> minutes";
			$trial_product = (isset($details['trial_product'])) ? $details['trial_product'][0] : 0;
			echo "<br/>Trial Product: <input type='number' value='".$trial_product."' name='trial_product'>";
			if($variations != NULL){//add these details in separate function
				echo "<br/><i>Use the variations tab below to add class times and trainers.</i>";
			}else{
				if(!isset($details['is_casual'])){//and post type != variable product
					//<input type="time" value="13:00" step="900">
					$start_time = (isset($details['start_time'])) ? $details['start_time'][0] : 0;
					echo "<br/>Start Time: <input type='time' value='".$start_time."' name='start_time' id='start_time'>";
					echo "<br/>Trainers:";
					$trainer1 = (isset($details['trainer1'])) ? $details['trainer1'][0] : NULL;
					crm_trainer_dropdown_select("trainer1", $trainer1);
					$trainer2 = (isset($details['trainer2'])) ? $details['trainer2'][0] : NULL;
					crm_trainer_dropdown_select("trainer2", $trainer2);
				}
			}	
		}else{
		/**
		 * Private bookings
		 */
			if($variations != NULL){//add these details in separate function
				echo "<br/><i>Use the variations tab below to add booking information.</i>";
			}else{
				$duration = (isset($details['duration'])) ? $details['duration'][0] : 0;
			echo "<br/>Class length: <input type='number' value='".$duration."' name='duration' id='duration'> minutes";
			}	
		}
		

		
		
	
	}
	submit_button();
}

function product_details_save($post_id){
    if (array_key_exists('is_bookable', $_POST)) {
        update_post_meta($post_id,'is_bookable', $_POST['is_bookable']);
    }
    if (array_key_exists('is_casual', $_POST)) {
        update_post_meta($post_id,'is_casual', $_POST['is_casual']);
	}
	if (array_key_exists('is_booking', $_POST)) {
        update_post_meta($post_id,'is_booking', $_POST['is_booking']);
    }
    if (array_key_exists('duration', $_POST)) {
        update_post_meta($post_id,'duration', $_POST['duration']);
    }
    if (array_key_exists('age_slug', $_POST)) {
        update_post_meta($post_id,'age_slug', $_POST['age_slug']);
    }
	if (array_key_exists('trial_product', $_POST)) {
        update_post_meta($post_id,'trial_product', $_POST['trial_product']);
    }
    if (array_key_exists('start_time', $_POST)) {
        update_post_meta($post_id,'start_time', $_POST['start_time']);
    }
    if (array_key_exists('trainer1', $_POST)) {
        update_post_meta($post_id,'trainer1', $_POST['trainer1']);
    }
    if (array_key_exists('trainer2', $_POST)) {
        update_post_meta($post_id,'trainer2', $_POST['trainer2']);
    }
}

function variation_settings_fields( $loop, $variation_data, $variation ) {
	$post_details = get_post_meta(get_the_ID());
	/**
	 * Only show on products used by CRM
	 */
	if(isset($post_details['is_bookable']) && $post_details['is_bookable']){
		$details = $variation_data;
		//var_dump($post_details);
		//echo $variation['post_parent'];
		if(isset($post_details['is_booking'])){
			$duration = (isset($details['duration'])) ? $details['duration'][0] : 0;
			$duration_name = "duration[".$variation->ID."]";
			echo "<br/>Duration: <input type='number' value='".$duration."' name='".$duration_name."' id='duration'> minutes";
		}elseif(!isset($post_details['is_casual']) && !isset($post_details['is_booking'])){//casual info set on schedule page
			$start_time = (isset($details['start_time'])) ? $details['start_time'][0] : 0;
			$stname = "start_time[".$variation->ID."]";
			echo "<br/>Start Time: <input type='time' value='".get_post_meta($variation->ID, 'start_time', true)."' name='".$stname."'>";// readonly
			echo "<br/>Trainers:";
			$trainer1 = (isset($details['trainer1'])) ? $details['trainer1'][0] : NULL;
			$tr1name = "trainer1[".$variation->ID."]";
			crm_trainer_dropdown_select($tr1name, $trainer1);
			$trainer2 = (isset($details['trainer2'])) ? $details['trainer2'][0] : NULL;
			$tr2name = "trainer2[".$variation->ID."]";
			crm_trainer_dropdown_select($tr2name, $trainer2);
		}
	}	
}

function save_variation_settings_fields( $variation_id, $i ) {
	if ( empty( $variation_id ) ) return;
	$start = $_POST['start_time'][$variation_id];//have to use this, otherwise isset does weird things
    if ( isset( $start ) ) {
        update_post_meta( $variation_id, 'start_time', $_POST['start_time'][$variation_id] );        
    }
    $trainer1 = $_POST['trainer1'][$variation_id];
    if ( isset( $trainer1 ) ) {
        update_post_meta( $variation_id, 'trainer1', $_POST['trainer1'][$variation_id]);
    }
    $trainer2 = $_POST['trainer2'][$variation_id];
    if ( isset( $trainer2 ) ) {
        update_post_meta( $variation_id, 'trainer2', $_POST['trainer2'][$variation_id]);
	}
	$duration = $_POST['duration'][$variation_id];
    if ( isset( $duration ) ) {
        update_post_meta( $variation_id, 'duration', $_POST['duration'][$variation_id]);
    }
}

/**
*
* Add new class schedule
*
*/
function crm_add_new_class_schedule($product_id = NULL, $variation_id = NULL, $url = NULL){
	global $wpdb;
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"><?php 
	if($product_id == NULL){
		?><label for="class_name">Class: </label><select name= 'class_name'><?php
		$classes = $wpdb->get_results("SELECT DISTINCT class_title FROM {$wpdb->prefix}crm_class_list WHERE age_slug != 'kids' ORDER BY class_title ASC");
		foreach ($classes as $class){
			echo "<option value='".$class->class_title."'>".$class->class_title."</option>";
		} 
		echo "</select> Virtual Class: <input type='checkbox' name='virtual'>";
		//  or add new: <input type='text' name='new_class_name'> currently won't work because class info is pulled from db using class_title
	}
	?><br/><label for='semester'>Semester: </label><?php crm_semester_dropdown_select("semester", "future"); ?> <label for='new_class_start'>Start:</label><input type="date" name="new_class_start"> <label for='new_class_time'>Time:</label><input type="time" name="new_class_time"> 
	<br/><label for='trainer1'>Trainer 1: </label><?php crm_trainer_dropdown_select("trainer1"); ?> <label for='trainer2'>Trainer 2: </label><?php crm_trainer_dropdown_select("trainer2");
	if($url != NULL){echo "<input type='hidden' name='url' value='".$url."'>"; }
	?><input type="hidden" name="action" value="add_new_schedule">
	<br/><div style="clear:both;"><input type="submit" value="Add schedule"></div>
	</form><?php
}

function add_new_schedule(){
	global $wpdb;
	if(!isset($_POST['product_id'])){//get product info from db
		$class_name = ($_POST['new_class_name']) ? $_POST['new_class_name'] : $_POST['class_name'];//add new title or use existing name
		$class_info = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}crm_class_list WHERE class_title = '$class_name' ORDER BY session_date DESC LIMIT 1");//DESC == most recent
		if(isset($_POST['virtual']) && $_POST['virtual'] == true){
			$class_id = 2;
		}elseif($class_name == "Open Training"){
			$class_id = 0;
		}else{
			$class_id = 1;
		}
		$duration = $class_info->duration;
		$age_slug = $class_info->age_slug;
		$prod_id = $class_info->prod_id;
	}else{//get post meta
		$class_name = get_the_title($_POST['product_id']);
		$details = get_post_meta($_POST['product_id']);
		$duration = $details['duration'][0];
		$age_slug = (isset($details['age_slug'])) ? $details['age_slug'][0] : "kids";
		$trial_product = get_post_meta($_POST['product_id'], 'trial_product', true );
		$prod_id = (isset($trial_product) && $trial_product >= 1) ? serialize(array($_POST['product_id'], $trial_product)) : serialize(array($_POST['product_id']));
		$class_id = (isset($_POST['class_id'])) ? $_POST['class_id'] : $_POST['product_id'];
	}

	$table = $wpdb->prefix.'crm_class_list';
	$new_date = $_POST['new_class_start']." ".$_POST['new_class_time'];
	if($_POST['semester'] <= 2){
		$semester_end = $new_date;
	}else{
		$semester_end = $_POST['semester'];
		$semester_slug = $wpdb->get_var("SELECT semester_slug FROM {$wpdb->prefix}crm_semesters WHERE semester_end >= '$semester_end'");
	}
	
	$trainers = array($_POST['trainer1'], $_POST['trainer2']);
	while($new_date <= $semester_end){
		$data = array(
			'age_slug' => $age_slug,
			'prod_id' => $prod_id,//serialized, column format must be text, not int
			'class_id' => $class_id,
			'class_title' => $class_name,
			'location' => "Circus Akimbo - Hornsby",
			'session_date' => $new_date,
			'duration' => $duration,
			'trainers' => serialize($trainers),
			);
		if(isset($semester_slug)){$data['semester_slug'] = $semester_slug;}
		$result = $wpdb->insert($table, $data);
		$new_date = date("Y-m-d-H:i", strtotime($new_date) + 604800);//add number of seconds in 7 days, g:ia time format 6:00pm
	}
	$message = ($result) ? "success" : "failure";
	$url = ($_POST['url'] != NULL) ? $_POST['url']."&message=".$message : get_site_url()."/wp-admin/admin.php?page=akimbo-crm2&tab=schedule&message=".$message;	
	wp_redirect( $url ); 
	exit;
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

function crm_semester_dropdown_select($name, $period = all, $single = true){//($variation_id, $period = "all", $limit = NULL){
	global $wpdb;
	echo "<select name= '".$name."'>";
	$query = "SELECT * FROM {$wpdb->prefix}crm_semesters";
	if($period == "future"){
		$today = current_time("Y-m-d");
		$query .= " WHERE semester_end >= '$today' ";
	}
	$query .= " ORDER BY semester_slug";
	$semesters = $wpdb->get_results($query);
	foreach ($semesters as $semester){echo "<option value='".$semester->semester_end."'>".$semester->semester_slug."</option>";}
	if($single == true){echo "<option value='1'>Single session</option>";}
	echo "</select>";
}

function weeks_between($datefrom, $dateto){//accepted format = Y-m-d
	$difference        = (strtotime($dateto) - strtotime($datefrom)) + 86400; // Difference in seconds, plus one day to round up to a full week
	$days_difference  = $difference / 86400; //floor($difference / 86400);
        $weeks_difference = floor($days_difference / 7); //ceil($days_difference / 7); // Complete weeks, rounds up to nearest week. Use floor to round down
	$days_remainder   = floor($days_difference % 7);
	$weeks_difference .= ($days_remainder >= 1) ? " weeks, ".$days_remainder." days" : " weeks";

    return $weeks_difference;
}

function add_new_class_name(){//haven't tested this or built the function to insert into db
	?><form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
	<br/>Add new class: <input type="text" name="class_title"> 
	Product: <select name= 'product_type'><?php
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
	<input type="submit" value="Add Class"></form><-- create separate function
	<?php
}

function crm_calculate_pro_rata_price(){
	$new_weeks = 9;	
	$product_price = 180;
	$weeks = 10;
	$new_total = ($product_price/$weeks) * $new_weeks;
	$new_cost = ($new_total/11)*10;
	$new_GST = ($new_total/11)*1;
	$result = array(
		'total' => $new_total,
		'cost' => $new_cost,
		'GST' => $new_GST,
	);
	return $result;
}