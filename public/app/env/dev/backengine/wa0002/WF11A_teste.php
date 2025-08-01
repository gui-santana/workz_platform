<?
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';

class WF11{
	public function diasUteis($DTd){		
		$wdf = search('app', 'wa0013_Feriados', '', "cmp = '{$DTd}'")[0];		
		$wad = $wdf['d1q'];
		$wbd = $wdf['d2q'];		
		$resulty = array($wad, $wbd);		
		return $resulty;
	}
	public function WF11A($um, $dx, $altdt, $mes, $dia, $ano, $vlLiberado, $txdt, $txvl, $txSpread, $tc, $tl, $tt, $tm, $lib0, $libx, $libp, $di, $i, $v, $J2Q, $ma, $vlLib, $dun, $dac, $jbc){
		$dtPrest = date("d/m/Y",mktime(0,0,0,$mes,$dia,$ano));
		$DT0 = date('Y-m-15', mktime(0,0,0,$mes,$dia,$ano));
		$DT1 = date('Y-m-d', strtotime('-15 days', strtotime($DT0))); //Último dia do mês anterior
		$DT2 = date('Y-m-'.date('t', mktime(0,0,0,$mes,'01',$ano)), strtotime($DT0)); //Último dia do mês atual
		$DT3 = date('Y-m-d', strtotime('-1 month', strtotime($DT0))); //Dia 15 do mês anterior
		
		// 1. TJLP										
		if($um == 1 || $um == 3){
			$J0Q = 0;
			$JAC = 0;			
			// 1.1 TJLP - BD MOEDA CONTRATUAL
			// Obs.: Os meses 1, 4, 7 e 10 são aqueles em que a TJLP é divulgada
			if($mes == 1 || $mes == 4 || $mes == 7 || $mes == 10){
				$tjmt = date("d/m/Y",mktime(0,0,0,$mes,1,$ano));
				$tjma = date("d/m/Y", strtotime('-3 months', mktime(0,0,0,$mes,1,$ano)));
			}elseif($mes == 2 || $mes == 5 || $mes == 8 || $mes == 11){
				$tjmt = date("d/m/Y", strtotime('-1 months', mktime(0,0,0,$mes,1,$ano)));
				$tjma = $tjmt;
			}elseif($mes == 3 || $mes == 6 || $mes == 9 || $mes == 12){
				$tjmt = date("d/m/Y", strtotime('-2 months', mktime(0,0,0,$mes,1,$ano)));
				$tjma = $tjmt;
			}
			//1.1.1 TJLP - BUSCA POSIÇÃO DA DATA NO BD
			$dpos = key(array_filter($txdt, function ($txdt_f) use ($tjmt){
				return $txdt_f == $tjmt;
			}));			
			$d1qpos = key(array_filter($txdt, function ($txdt_f) use ($tjma){
				return $txdt_f == $tjma;
			}));			
			//1.1.2 TJLP - A ÚLTIMA DIVULGAÇÃO ESTÁ NA POSIÇÃO "ZERO" DO BD
			//Obs.: Se a busca pela posição da data não retornar valor, o cálculo deverá assumir o valor mais recente (último valor cadastrado) ***MUDAR PARA: Quando a data não retornar valor, assumir o valor correspondente à data do PERÍODO SELECIONADO.
			if($dpos == ""){
				$tjtx = (str_replace(",",".",end($txvl))/100);
			}else{
				$tjtx = (str_replace(",",".",$txvl[$dpos])/100);
			}
			if($d1qpos == ""){
				$tjta = (str_replace(",",".",end($txvl))/100);
			}else{
				$tjta = (str_replace(",",".",$txvl[$d1qpos])/100);
			}			
			//1.1.3 TJLP - TAXA ASSUMIDA NO PERÍODO
			//Obs.: Se a taxa encontrada for menor do que 6% a.a., o cálculo deverá assumir o valor desta. Caso contrário, o cálculo deverá assumir o valor de 6%																													
			if($tjtx < 0.06){
				$tjtx = $tjtx;
			}else{
				$tjtx = 0.06;
			}
			if($tjta < 0.06){
				$tjta = $tjta;
			}else{
				$tjta = 0.06;
			}					
			$txTJLP = $tjtx;			
			//1.1.4 - TJLP - CÁLCULO DA TAXA EFETIVA											
			$txEfetiva = (pow((1 + ($txTJLP + $txSpread)),(1/12))-1);
			$dz = 30;
			$tx2Q = (pow((1 + ($txTJLP + $txSpread)),(15/360))-1);
			// 1.2 TJLP - CÁLCULO DURANTE PERÍODO DE CARÊNCIA
			//Obs.: i é menor do que o número de meses em carência, mais 1
			//Obs.: Pagamento TRIMESTRAL de juros. Não há pagamento de principal durante este período.	
			if($i < ($tc + 1)){
				$Df = (30 - $dx);
				//Se a liberação ocorrer no dia 31, o cálculo assume o valor equivalente ao dia 30 - 0 dias para o acumulo de juros
				if($Df < 0){
					$Df = 0;
				}	
				$prest = 0; //Pgto de Principal = 0
				$tx15D = (pow((1 + ($txTJLP + $txSpread)),(15/360))-1); //Taxa Efetiva de 1Q				
				$in = (($ma + 1) % 3); //Resto da divisão entre o número de meses na carência, por 3 (Pagamento Trimestral)				
				//1.2.1 TJLP - MESES EM QUE HÁ PAGAMENTO TRIMESTRAL DE JUROS				
				if($mes == ($in + 1) || $mes == ($in + 4) || $mes == ($in + 7) || $mes == ($in + 10)){					
					$J1Q = (($vlLiberado + $jbc) * $tx15D); //Juros acumulados em 90 dias, menos juros acumulados em 75 dias					
					$J2Q = round(($vlLiberado*$tx2Q),8); //Juros acumulados em 15 dias (ou menos, se Fevereiro)
					$JPG = $jbc + $J1Q; //Juros a pagar, acumulados em 90 dias
					$IVM = 0;					
					if($i == 1){
						//1.2.1.1.2 - PAGAMENTO PROPORCIONAL A SER REALIZADO NO MÊS DE LIBERAÇÃO (x) <= 15
						$J1Q = 0;
						$J2Q = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),($Df/360))-1))),8);
						$JPG = 0;
						$jbc = 0;						
					}	
					//1.2.1.2 TJLP - JUROS ACUMULADOS, EM 1A E 2A QUINZENAS
					//Obs.: Quando há o pagamento trimestral dos juros, zeramos o contador do período de carência					
					$v = 0; //Contador do período de carência
					$jbc = $J2Q;
				//1.2.2 TJLP - MESES EM QUE NÃO HÁ PAGAMENTO TRIMESTRAL DE JUROS (ACUMULO DE JUROS)											
				}else{
					//1.2.2.1 TJLP - ACUMULO PROPORCIONAL DOS JUROS - MÊS DE LIBERAÇÃO
					if($i == 1){
						$IVM = 0;
						$JPG = 0;
						$J1Q = 0;														
						$J2Q = round(($vlLiberado*(pow((1 + ($txTJLP + $txSpread)),($Df/360))-1)),8);
						$jbc = $J2Q;						
					//1.2.2.2 TJLP - ACUMULO DE JUROS (1A E 2A QUINZENAS)
					}else{						
						$J1Q = round((($vlLiberado + $jbc) * $tx15D),8);
						$J2Q = round((($vlLiberado + $jbc + $J1Q) * $tx15D),8);
						$S1Q = round((($vlLiberado + $jbc) + $J1Q),8);
						$jbc = round($jbc + $J1Q + $J2Q,8);						
						$IVM = round(($S1Q - $vlLiberado),8);
						$JPG = 0;
					}
					//Obs.: Quando os juros totais são acumulados, somamos um mês ao contador do período de carência
					$v = $v + 1; //Contador do período de carência													
				}											
			// 1.3 TJLP - CÁLCULO DURANTE AMORTIZAÇÃO DE PRINCIPAL E JUROS	
			//Obs: (i) maior que numero de meses durante carência e menor ou igual que o número total de meses												
			}elseif($tc < $i && $i <= $tt){
				$IVM = 0;
				$JAC = 0;
				$ParcRest = ($tt + 1 - $i); //Qde de parcelas restantes											
												
				//Obs.: Qdo a data de liberação é menor que 15 ele continua acumulando normalmente no mes de liberação												
				if($i == 1){
					$JPG = 0;
					$J1Q = 0; //Mudar p/ cálculo antes do dia 15
					$J2Q = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(15/360))-1))),8); //Mudar p/ cálculo após o dia 15
					$prest = 0;
				}else{
					if($i == 2 && $altdt == ""){
						$JPG = round(($vlLiberado*((pow((1 + ($txTJLP + $txSpread)),(15/360))-1))) + $J2Q,8);
					}else{
						//O cálculo real deve considerar as taxas de 1Q e 2Q, conforme o seguinte cálculo:
						//JPG1Q = vlLiberado * tx1Q
						//JPG2Q = (vlLiberado + JPG1Q) * tx2Q
						//JPG = JPG1Q + JPG2Q
						$JPG1Q = $vlLiberado * (pow((1 + ($tjta + $txSpread)),(15/360))-1);
						$JPG2Q = ($vlLiberado + $JPG1Q) * (pow((1 + ($tjtx + $txSpread)),(15/360))-1);
												
						
						//QUANDO A TJLP MUDA ABAIXO DE 6%
						if( (($tjta <> $tjtx) && ($tjta < 0.06) &&  ($tjtx < 0.06)) || ($tjta >= 0.06 && $tjtx < 0.06) || ($tjta < 0.06 && $tjtx >= 0.06) ){
							//Juros 1Q
							$JPG1Q = $vlLiberado * (pow((1 + ($tjta + $txSpread)),(15/360))-1);
							//Juros no dia 1º (dia da divulgação -> utiliza a taxa anterior)
							$JPG2Q_A = ($vlLiberado + $JPG1Q) * (pow((1 + ($tjta + $txSpread)),(1/360))-1);
							//Juros, do dia 2 ao dia 15
							$JPG2Q_B = ($vlLiberado + $JPG1Q + $JPG2Q_A) * (pow((1 + ($tjtx + $txSpread)),(14/360))-1);
							//Juros, do dia 1º ao dia 15
							$JPG2Q = $JPG2Q_A + $JPG2Q_B;												
						}						
						$JPG = round(($JPG1Q + $JPG2Q),8);
																						
						//Cálculo considerando uma única taxa
						//$JPG = round(($vlLiberado*$txEfetiva),8);	
					}
												
					$J0Q = round(($vlLiberado * (pow((1 + ($tjta + $txSpread)),(15/360))-1)),8); //Juros 2Q Mês Anterior
					//$J1Q = $J0Q;
					
					$J1Q = ($JPG-$J0Q); //Juros 1Q
														
					$prest = round((($vlLiberado * ($txEfetiva * pow(1 + $txEfetiva, $ParcRest))/(pow(1 + $txEfetiva, $ParcRest) - 1)) - ($vlLiberado*$txEfetiva)),8); //Valor da amorização de principal
					$vlLiberado = round(($vlLiberado - $prest),8); //Saldo de PRINCIPAL VINCENDO, menos o pagamento do PRINCIPAL
					$J2Q = round(($vlLiberado * $tx2Q),8); //Juros 2Q Mês Atual									
				}						
			}
			$APFNA = 0;
			$SDV1Q = 0;
			$SDV2Q = 0;
			$SDVFN = 0;
		
		// 2. IPCA
		}elseif($um == 2){		
			$d1qs = WF11::diasUteis($DT0)[0];
			$d2qs = WF11::diasUteis($DT0)[1];			
			$d1qc = $d1qs;			
			if($i == 1){
				if($dun < 0){
					$d1qs = - $dun;
				}elseif($dun > 0){
					$d1qs = 0;
					$d2qs = $d2qs - $dun;
				}else{
					$d1qs = 0;
				}
			}			
			$wm = $dac;
			$wd = $dac + $d1qs;			
			if($i > ($tc - 12) && $mes == 1){
				$dac = 0;
				$d1qs = 0;
			}			
			$wf = $dac + $d1qs + $d2qs;			
			$tx =  $libx;			
			$SDV1Q = round($vlLib * round(pow((1 + $tx), ($wm / 252)),6),8);			
			$SDVFN = round($vlLib * round(pow((1 + $tx), ($wf / 252)),6),8);
			$SDV2Q = round($vlLib * round(pow((1 + $tx), ($wd/ 252)),6),8);					
			if($i < ($tc + 1)){			
				$prest = 0;
				$JPG = 0;
				$IVM = 0;		
				if($i == ($tc - 11)){				
					$JPG = $SDV2Q - $vlLib;					
				}				
				$J2Q = $SDVFN - $SDV2Q + $JPG;				
			}elseif($tc < $i && $i <= $tt){				
				if($mes == 1){
					if($i > $tc && date('n', strtotime($DT0)) == 1){
						$JPG = $SDV2Q - $vlLib;
						$prest = round(($vlLiberado / ($libp + 1)),8);
						$vlLib = round(($vlLib - $prest),8);
						$J2Q = round($vlLib * round(pow((1 + $tx), ($d2qs / 252)) - 1, 6),8);
					}else{
						$JPG = 0;
						$prest = 0;
						$J2Q = $SDVFN - $SDV2Q + $JPG;
					}					
				}else{
					$JPG = 0;
					$prest = 0;
					$J2Q = $SDVFN - $SDV2Q + $JPG;
				}
				$IVM = 0;
			}	
			$dac = $dac + $d1qs + $d2qs;
			$J1Q = ($SDV2Q - $SDV1Q);
			$JAC = ($SDVFN - $vlLib);
			$APFNA = 0;			
			$J0Q = $SDV1Q - $vlLib;
			
		// 3. TLP IPCA
		}elseif($um == 4){
			include('WF11B.php');
		}
		$resultx[] = array($JPG, $J1Q, $J2Q, $dtPrest, $vlLiberado, $prest, $i, $v, $J0Q, $DT2, $IVM, $vlLib, $APFNA, $SDV1Q, $SDV2Q, $SDVFN, $dac, $jbc, $JAC);
		return $resultx;
	}
}
?>