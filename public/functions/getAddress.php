<?php
if(isset($_SESSION['geolocation']) && !empty($_SESSION['geolocation'])){
	$geo = explode(';', $_SESSION['geolocation']);
	$json = file_get_contents('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude='.$geo[0].'&longitude='.$geo[1].'&localityLanguage=pt');
	$geolocation = json_decode($json, true);
	$_SESSION['city'] = $geolocation['city'];
}
?>