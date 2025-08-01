<?
	function searchEvents($day){		
		include($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/config.php');
		$lfDate = $events->prepare("SELECT * FROM events WHERE us = '".$_SESSION['wz']."' AND DATE(dt) = DATE('".$day."') ORDER BY dt ASC");
		$lfDate->execute();
		$rwDate = $lfDate->rowCount(PDO::FETCH_ASSOC);		
		return $rwDate;
	}
?>