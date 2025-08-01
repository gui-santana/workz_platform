<?php
function getCurrentURL($type) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    if($type == 1){
		return $protocol . "://" . $_SERVER['HTTP_HOST'];
	}elseif($type == 2){
		return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}elseif($type == 3){
		return $_SERVER['REQUEST_URI'];
	}	
}
?>