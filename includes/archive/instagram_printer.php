<?php

$client_id = "fc0b1c4bb84045daba24bc741201f7f2";
$site = get_site_url();
$redirect_uri = $site."/wp-admin/admin.php?page=akimbo-crm&tab=instagram";

$access_token = "1611754706.fc0b1c4.2b25061740d54699847602970c3c5869";



echo "<h2>Instagram Printer</h2>";
if(!isset($access_token)){
	echo "<a href='https://api.instagram.com/oauth/authorize/?client_id=".$client_id."&redirect_uri=".$redirect_uri."&response_type=token'><button>Authorize</button></a>";
}


$link = "https://api.instagram.com/v1/self/media/recent?access_token=".$access_token;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.instagram.com/v1/self/media/recent?access_token='.$access_token;
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Basic '. $auth));
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");//this is the important bit!! GET/PATCH
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

//https://www.instagram.com/developer/authentication/