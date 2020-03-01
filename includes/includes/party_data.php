<?php
global $wpdb;
//global $woocommerce;
//$today = current_time('mysql' );
$today = current_time('Y-m-d-h:ia');

//initialize CSV writing
$path = wp_upload_dir();   // or where ever you want the file to go
$outstream = fopen($path['path']."/partydata.csv", "w");  // the file name you choose
$fields = array('customer', 'item-id', 'date', 'order_date', 'total');  // the information you want in the csv file
fputcsv($outstream, $fields);  //creates the first line in the csv file

$party_ids = get_posts( array(
        'post_type' => 'product',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
		'product_cat' => 'parties',
) );


echo "<br/><table border='1'><tr><td>Customer</td><td>Product</td><td>Date</td><td>Order Date</td><td>Subtotal</td></tr>";
$recorded_orders = array();
foreach($party_ids as $product_id){
	$parties = get_all_orders_from_a_product_id($product_id);
	foreach($parties as $party){
		$order = wc_get_order( $party->ID );
		$prod_id = $party->ID;
		if(!in_array($party->ID, $recorded_orders)){
			$recorded_orders[] = $party->ID;
			$items = $order->get_items();
			foreach($items as $item){
				$item_product_id = $item['product_id'];
				if(in_array($item_product_id, $party_ids)){
					echo "<tr><td>".$order->get_customer_id()."</td>";
					echo "<td>".$item['name']."</td>";
					echo "<td>".$item['_book_date']."</td>";
					echo "<td>".$party->post_date."</td>";
					echo "<td>".$item['subtotal']."</td></tr>";
					fputcsv($outstream, array($order->get_customer_id(), $item['name'], $item['_book_date'], $party->post_date, $item['subtotal']));
				}
				
			}
		}
		
		
		
	}
}


echo "</table>";

//var_dump($recorded_orders);

//https://businessbloomer.com/woocommerce-easily-get-order-info-total-items-etc-from-order-object/
	
fclose($outstream); 
echo '<a href="'.$path['url'].'/partydata.csv">Download as csv</a>';  //make a link to the file so the user can download.