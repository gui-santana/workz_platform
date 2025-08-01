<?
function nSubord($nv){
	if($nv == 1){
		$ns = "Operador";
	}elseif($nv == 2){
		$ns = "Procurador";
	}elseif($nv == 3){
		$ns = "Estatutário";
	}
	return $ns;
}
?>