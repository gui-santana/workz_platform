<?
function taskStatus($st){
	if($st == 0){ 
		return 'Não iniciada';
	}elseif($st == 1){ 
		return 'Em execução: pausada';
	}elseif($st == 2){ 
		return 'Em execução: executando';
	}elseif($st == 3){ 
		return 'Finalizada';
	}elseif($st == 5){ 
		return 'Finalizada: arquivada';
	}elseif($st == 6){ 
		return 'Arquivada';
	}elseif($st == 99){ 
		return 'Arquivada';
	}
}
?>