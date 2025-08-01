<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');
include('../../config/app_config.php');
include('../../auth/token_storage.php');
if(!isset($_SESSION)){
	session_start();
	//FUNCTIONS
	include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
}
if (isset($_GET['goalType'])) {
    $goalType = $_GET['goalType'];
    // Determina o tipo de meta pai com base no tipo selecionado
    switch ($goalType) {
        case '1':
            $parentGoalType = 0;
            break;
        case '2':
		case '6':
            $parentGoalType = 1;
            break;
        case '3':
            $parentGoalType = 2;
            break;
		case '4':
		case '5':			
			$parentGoalType = 3;
			break;
        default:
            $parentGoalType = '';
            break;
    }
    if ($parentGoalType !== '') {		
		$parentGoals = search('app', 'wa0008_goals', 'id,nm', "us = '".$_SESSION['wz']."' AND tp = ".$parentGoalType."");
        echo '<option value="">Select Parent Goal</option>';        		
		foreach($parentGoals as $Goal){
			echo '<option value="' . $Goal['id'] . '">' . $Goal['nm'] . '</option>';
		}        
    } else {
        echo '<option value="">No Parent Goal</option>';
    }
}
?>
