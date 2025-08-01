<?php
	session_start();
	$session_data = json_decode($_POST['vr'], true);	
	$_SESSION['wz'] = $session_data['user_id'];						
	$_SESSION['geolocation'] = $session_data['geolocation'];
	exit();
?>