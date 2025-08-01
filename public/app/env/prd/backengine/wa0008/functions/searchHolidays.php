<?
	function searchHolidays($day, $db){
		$lfDate = $db->prepare("SELECT * FROM holidays_br WHERE DATE(dt) = DATE('".$day."')");
		$lfDate->execute();
		$rwDate = $lfDate->rowCount(PDO::FETCH_ASSOC);
		if($rwDate > 0){
			$ftDate = $lfDate->fetch(PDO::FETCH_ASSOC);
			$result = array(
				'st' => 1,
				'ds' => $ftDate['ds']
			);
		}else{
			$result = array(
				'st' => 0,
				'ds' => ''
			);
		}
		return $result;
	}
?>