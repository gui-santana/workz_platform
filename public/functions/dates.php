<?
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL,'pt_BR.UTF8');
mb_internal_encoding('UTF8'); 
mb_regex_encoding('UTF8');

function post_dates($date){
	$now = strtotime(date('Y-m-d H:i:s'));
	$min = strtotime(date('Y-m-d H:i:s', strtotime('-1 minute')));
	$dia = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
	$sem = strtotime(date('Y-m-d 23:59:59', strtotime('-1 week')));
	$ano = strtotime(date('Y-12-31 23:59:59', strtotime('-1 year')));
	$dpl = strtotime($date);

	if($dpl > $min){
		$dtxt = 'Agora mesmo';
	}elseif($dpl > $dia){
		$dtxt = strftime('às %H:%M', strtotime($date));
	}elseif($dpl > $sem){
		$dtxt = strftime('%A, às %H:%M', strtotime($date));
	}elseif($dpl > $ano){
		$dtxt = strftime('%e de %B, às %H:%M', strtotime($date));
	}else{
		$dtxt = strftime('%A, %e de %B de %Y, às %H:%M', strtotime($date));
	}
	
	return $dtxt;
}
?>