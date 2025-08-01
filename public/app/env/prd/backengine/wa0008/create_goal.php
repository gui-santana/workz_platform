<?php
include('../../../sanitize.php');
include('../../config/app_config.php');
include('../../auth/token_storage.php');

if(!isset($_SESSION)){
    session_start();
    include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
	
}
include($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $goal = insert('apps', 'wa0008_goals', 'tp, pa, us, ds, tg, dt', '"'.$_POST['goalType'].'"', '"'.$_POST['parentGoal'].'"', '"'.$_SESSION['wz'].'"', '"'.$_POST['description'].'"', '"'.$_POST['deadline'].'"', '"'.$_POST['startDate'].'"');

	if($goal){
		echo 'Goal '.$goal.' created successfully!';
	}
	
} else {
    echo "Invalid request method.";
}
?>
