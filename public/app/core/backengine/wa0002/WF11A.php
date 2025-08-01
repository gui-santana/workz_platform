<?
function WF11A($um, $dx, $altdt, $mes, $dia, $ano, $vlLiberado, $txdt, $txvl, $txSpread, $tc, $tl, $tt, $tm, $lib0, $libx, $libp, $di, $i){

$dtPrest = date("d/m/Y",mktime(0,0,0,$mes,$dia,$ano));
$DT0 = date('Y-m-15', mktime(0,0,0,$mes,$dia,$ano));
$DT1 = date('Y-m-d', strtotime('-15 days', strtotime($DT0))); //Último dia do mês anterior
$DT2 = date('Y-m-'.date('t', mktime(0,0,0,$mes,'01',$ano)), strtotime($DT0)); //Último dia do mês atual
$DT3 = date('Y-m-d', strtotime('-1 month', strtotime($DT0))); //Dia 15 do mês anterior
																					
// 1. TJLP										
if($um == 1){
												
	// 1.1 TJLP - BD MOEDA CONTRATUAL
	// Obs.: Os meses 1, 4, 7 e 10 são aqueles em que a TJLP é divulgada
					
	if($mes == 1 || $mes == 4 || $mes == 7 || $mes == 10){
		$tjmt = date("d/m/Y",mktime(0,0,0,$mes,1,$ano));
	}elseif($mes == 2 || $mes == 5 || $mes == 8 || $mes == 11){
		$tjmt = date("d/m/Y", strtotime('-1 months', mktime(0,0,0,$mes,1,$ano)));
	}elseif($mes == 3 || $mes == 6 || $mes == 9 || $mes == 12){
		$tjmt = date("d/m/Y", strtotime('-2 months', mktime(0,0,0,$mes,1,$ano)));
	}
												
	//1.1.1 TJLP - BUSCA POSIÇÃO DA DATA NO BD
												
	$dpos = key(array_filter($txdt, function ($txdt_f) use ($tjmt){
		return $txdt_f == $tjmt;
	}));
												
	//1.1.2 TJLP - A ÚLTIMA DIVULGAÇÃO ESTÁ NA POSIÇÃO "ZERO" DO BD
	//Obs.: Se a busca pela posição da data não retornar valor, o cálculo deverá assumir o valor mais recente (último valor cadastrado)											
	if($dpos == ""){
		$tjtx = (str_replace(",",".",end($txvl))/100);
	}else{
		$tjtx = (str_replace(",",".",$txvl[$dpos])/100);
	}
												
	//1.1.3 TJLP - TAXA ASSUMIDA NO PERÍODO
	//Obs.: Se a taxa encontrada for menor do que 6% a.a., o cálculo deverá assumir o valor desta. Caso contrário, o cálculo deverá assumir o valor de 6%																													
	if($tjtx < 0.06){
		$tjtx = $tjtx;
	}else{
		$tjtx = 0.06;
	}
												
	$txTJLP = $tjtx;
												
	//1.1.4 - TJLP - CÁLCULO DA TAXA EFETIVA											
												
	$txEfetiva = (pow((1 + ($txTJLP + $txSpread)),(1/12))-1);
												
	//Obs.: A Segunda Quinzena de Fevereiro deverá assumir um número de dias menor do que 30, de acordo com o ano.
										
	$dz = 30; //Último dia considerado
	$tx2Q = (pow((1 + ($txTJLP + $txSpread)),(15/360))-1);
											
	// 1.2 TJLP - CÁLCULO DURANTE PERÍODO DE CARÊNCIA
	//Obs.: i é menor do que o número de meses em carência, mais 1
	//Obs.: Pagamento TRIMESTRAL de juros. Não há pagamento de principal durante este período.	
												
	if($i < ($tc + 1)){
		
		$v = 0; //Contador do período de carência
													
		$Df = (30 - $dx);
		//Se a liberação ocorrer no dia 31, o cálculo assume o valor equivalente ao dia 30 - 0 dias para o acumulo de juros
		if($Df < 0){
			$Df = 0;
		}
													
		$prest = 0; //Pgto de Principal = 0
		$tx15D = (pow((1 + ($txTJLP + $txSpread)),(15/360))-1); //Taxa Efetiva de 1Q

		$in = ($tl % 3); //Resto da divisão entre o número de meses na carência, por 3 (Pagamento Trimestral)
													
		//1.2.1 TJLP - MESES EM QUE HÁ PAGAMENTO TRIMESTRAL DE JUROS
													
		if($mes == ($in + 1) || $mes == ($in + 4) || $mes == ($in + 7) || $mes == ($in + 10)){
														
			$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(90/360))-1))),4); //Juros a pagar, acumulados em 90 dias
														
			$CJP = $JPG; //?
													
			//1.2.1.1 TJLP - PAGAMENTO PROPORCIONAL DOS JUROS (DURANTE O MÊS DE LIBERAÇÃO)
			//Obs.: (dx) é o DIA do mês de liberação - Se (dx) = 31, deverá assumir 30
														
			//RESOLVER ISSO > ASSUME (i) = 1 pois NÃO HÁ NUMERAÇÃO NEGATIVA EM "for", ISSO IMPACTA CÁLCULOS POSTERIORES																							
			$jbc = ($vlLiberado*(pow((1 + ($txTJLP + $txSpread)),(75/360))-1)); //Juros acumulados em 75 dias
														
			if($i == 1){
				//1.2.1.1.2 - PAGAMENTO PROPORCIONAL A SER REALIZADO NO MÊS DE LIBERAÇÃO (x) <= 15
				$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),($Df/360))-1))),4);
				$jbc = $JPG;
			}elseif($i == 2){
				//1.2.1.1.1 - PAGAMENTO PROPORCIONAL A SER REALIZADO NO MÊS POSTERIOR À DATA DE LIBERAÇÃO (x + 15) <= 30
				$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(($Df + 15)/360))-1))),4);
				$jbc = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),($Df/360))-1))),4);
			}elseif($i == 3){
				//1.2.1.1.3 - PAGAMENTO PROPORCIONAL A SER RALIZADO NO SEGUNDO MÊS A PARTIR DO MÊS DE LIBERAÇÃO (x + 45) <= 60
				$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(($Df + 45)/360))-1))),4);
				$jbc = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(($Df + 15)/360))-1))),4);
			}
														
			//1.2.1.2 TJLP - JUROS ACUMULADOS, EM 1A E 2A QUINZENAS
			$J1Q = round(($JPG - $jbc), 4); //Juros acumulados em 90 dias, menos juros acumulados em 75 dias
			$J2Q = round(($vlLiberado*$tx2Q),4); //Juros acumulados em 15 dias (ou menos, se Fevereiro)
														
														
			//Obs.: Quando há o pagamento trimestral dos juros, zeramos o contador do período de carência
			
															
		}else{
														
			//1.2.2 TJLP - MESES EM QUE NÃO HÁ PAGAMENTO TRIMESTRAL DE JUROS (ACUMULO DE JUROS)

			//1.2.2.1 TJLP - ACUMULO PROPORCIONAL DOS JUROS - MÊS DE LIBERAÇÃO
			if($i == 1){
															
				$JPG = 0;
				$J1Q = 0;
				$J2Q = round(($vlLiberado*(pow((1 + ($txTJLP + $txSpread)),($Df/360))-1)),4);
				
			//1.2.2.2 TJLP - ACUMULO DE JUROS, EM 1A E 2A QUINZENAS
			}else{															
				$jbc = ($vlLiberado*(pow((1 + ($txTJLP + $txSpread)),(((abs($v)*30)+15)/360))-1));
				$JPG = 0;
				$J1Q = round((($vlLiberado+$jbc)*($tx15D)), 4); //Juros 1Q
				$J2Q = round((($vlLiberado+$jbc+$J1Q)*($tx15D)), 4); //Juros 2Q
			}
														
			//Obs.: Quando os juros totais são acumulados, somamos um mês ao contador do período de carência
			$v = $v + 1; //Contador do período de carência
														
		}
												
		// 1.3 TJLP - CÁLCULO DURANTE AMORTIZAÇÃO DE PRINCIPAL E JUROS	
		//Obs: (i) maior que numero de meses durante carência e menor ou igual que o número total de meses
												
	}elseif($tc < $i && $i <= $tt){
													
		$JAC = 0;
		$ParcRest = ($tt + 1 - $i); //Qde de parcelas restantes											
													
		//Obs.: Qdo a data de liberação é menor que 15 ele continua acumulando normalmente no mes de liberação												
		if($i == 1){
			$JPG = 0;
			$J1Q = 0; //Mudar p/ cálculo antes do dia 15
			$J2Q = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(15/360))-1))),4); //Mudar p/ cálculo após o dia 15
			$prest = 0;
		}else{
			$J2Q = 0;
			
			if($i == 2 && $altdt == ""){
				$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(15/360))-1))) + $J2Q,4);
			}else{
				$JPG = round(($vlLiberado*$txEfetiva),4);	
			}
														
			$J0Q = round(($vlLiberado*(pow((1 + ($txTJLP + $txSpread)),(15/360))-1)),4); //Juros 2Q Mês Anterior
			$J1Q = ($JPG-$J0Q); //Juros 1Q
														
			$prest = round((($vlLiberado * ($txEfetiva * pow(1 + $txEfetiva, $ParcRest))/(pow(1 + $txEfetiva, $ParcRest) - 1)) - ($vlLiberado*$txEfetiva)),4); //Valor da amorização de principal
			$vlLiberado = round(($vlLiberado - $prest),4); //Saldo de PRINCIPAL VINCENDO, menos o pagamento do PRINCIPAL
			$J2Q = round(($vlLiberado * $tx2Q),4); //Juros 2Q Mês Atual
													
		}																					
	}
												
//Se cálculo em IPCA
}elseif($um == 2){
	
	//Período entre o pagamento de juros na carência e o término da carência (12 meses)
	if($i <= ($tc - 11)){												
		$Xc = $lib0;																													
	}elseif(($tc - 11) < $i && $i <= $tc){	
		$Xc = date('Y-m-15', strtotime('-12 months', strtotime($di)));												
	}
												
	$wd = WF11::getWorkingDays($Xc, $DT0); //Dias úteis até 15
	$wm = WF11::getWorkingDays($Xc, $DT1); //Dias úteis até 01
	$wf = WF11::getWorkingDays($Xc, $DT2); //Dias úteis até 30
																		
	$tx =  $libx;
												
	if($i < ($tc + 1)){
																					
		$SDV1Q = $vlLiberado * pow((1 + $tx), ($wm / 252));
		$SDV2Q = $vlLiberado * pow((1 + $tx), ($wd / 252));
		$SDVFN = $vlLiberado * pow((1 + $tx), ($wf / 252));
																								
		//$JPG = round($vlLiberado * round((pow((1 + $tx), ($wf / 252)) - 1), 6), 4);
													
		$JPG = 0;
													
		$J0Q = round($vlLiberado * round((pow((1 + $tx), ($wm / 252)) - 1), 6), 4);
		$J1Q = (round($vlLiberado * round((pow((1 + $tx), ($wd / 252)) - 1), 6), 4) - $J0Q);
		$J2Q = (round($vlLiberado * round((pow((1 + $tx), ($wf / 252)) - 1), 6), 4) - $J1Q - $J0Q);	
													
		//$JPG = $wf;
		//$J1Q = $SDV2Q;
		//$J2Q = $SDVFN;
													
		if($i == ($tc - 11)){													
			$JPG = round($vlLiberado * round((pow((1 + $tx), ($wd / 252)) - 1), 6), 4);
			$J2Q = (round($vlLiberado * round((pow((1 + $tx), (($wf - $wd)/252)) - 1), 6), 4));	
		}
													
		$prest = 0;
													
	}elseif($tc < $i && $i <= $tt){
													
		$J1Q = 1;
		$J2Q = 1;
													
		if($mes == 1){
														
			if($i == ($tc + 1)){
				$vlLib = $vlLiberado;
			}
														
			$Xc = date('Y-m-d', strtotime('-12 months', strtotime($DT0)));
														
			$wd = getWorkingDays($Xc, $DT0); //Dias úteis até 15
														
			if(date('N', strtotime($DT0)) > 5){
				$wd = getWorkingDays($Xc, date('Y-m-d', strtotime('-11 days', strtotime($DT0))));
			}
			
														
			$JPG = round($vlLib * round((pow((1 + $tx), ($wd / 252)) - 1), 6), 4);
			$prest = round(($vlLiberado / ($libp + 1)), 4);
			$vlLib = ($vlLib - $prest);
														
		}else{
														
		$JPG = 0;
		$prest = 0;
														
		}	
	}

	$JPG = 0;
	$J1Q = 0;
	$J2Q = 0;
	$dtPrest = 0;
	$vlLiberado = 0;
	$prest = 0;
	
}

$resultx[] = array($JPG, $J1Q, $J2Q, $dtPrest, $vlLiberado, $prest);

return $resultx;

}
?>