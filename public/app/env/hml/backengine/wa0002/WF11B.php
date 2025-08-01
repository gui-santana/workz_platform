<?	
	$a2qs = WF11::diasUteis($DT3)[1]; //2a Quinzena do Mês Anterior
	$d1qs = WF11::diasUteis($DT0)[0];
	$d2qs = WF11::diasUteis($DT0)[1];
	
	$d1qc = $d1qs;
	
	$wm = $dac; //Dias Acumulados
	$wd = $dac + $d1qs; //Dias Acumulados + Dias de 1a Quinzena	
	$wf = $dac + $d1qs + $d2qs; //Dias Acumulados + Dias de 1a Quinzena + Dias de 2a Quinzena 
	$tx =  $libx;
	
	//$SDV1Q = round($vlLib * round(pow((1 + $tx), ($wm / 252)),6),8);	
	//$SDV2Q = round($vlLib * round(pow((1 + $tx), ($wd/ 252)),6),8);
	$SDVFN = round($vlLib * round(pow((1 + $tx), ($wf / 252)),8),8);
	
	$dac = 0;	
	$SDV2Q = $vlLib;
	
	//CARÊNCIA
	if($i < ($tc + 1)){
		//PRAZO DE CARÊNCIA		
		$prest = 0;
		$JPG = 0;			
		$in = (($ma + 1) % 3); //Resto da divisão entre o número de meses na carência, por 3 (Pagamento Trimestral)
				
		//1.2.1 TLP - MESES EM QUE HÁ PAGAMENTO TRIMESTRAL DE JUROS
		if($mes == ($in + 1) || $mes == ($in + 4) || $mes == ($in + 7) || $mes == ($in + 10)){
			//1Q
			$J1Q = round(((pow((1 + $tx), ($d1qs / 252)) - 1) * $SDV2Q),8);
			$JPG = $SDV2Q + $J1Q - $vlLiberado;			
			$SDV1Q = $vlLiberado;			
			//2Q
			$J2Q = round(((pow((1 + $tx), ($d2qs / 252)) - 1) * $SDV1Q),8);							
			$SDV2Q = $SDV1Q + $J2Q;														
			$v = 0;		
		}else{
			if($i == 1){
				if($dun < 0){
					//1Q
					$J1Q = round(((pow((1 + $tx), (abs($dun) / 252)) - 1) * $SDV2Q),8);
					$SDV1Q = $SDV2Q + $J1Q;
					//2Q					
					$J2Q = round(((pow((1 + $tx), ($d2qs / 252)) - 1) * $SDV1Q),8);
					$SDV2Q = $SDV1Q + $J2Q;
				}else{
					//1Q
					$J1Q = 0;
					$SDV1Q = $vlLiberado;
					//2Q
					$J2Q = round(((pow((1 + $tx), ($dun / 252)) - 1) * $SDV1Q),8);
					$SDV2Q = $vlLiberado + $J2Q;
				}													
			}else{
				//1Q
				$J1Q = round(((pow((1 + $tx), ($d1qs / 252)) - 1) * $SDV2Q),8);
				$SDV1Q = $SDV2Q + $J1Q;
				//2Q
				$J2Q = round(((pow((1 + $tx), ($d2qs / 252)) - 1) * $SDV1Q),8);
				$SDV2Q = $SDV1Q + $J2Q;						
			}							
			$JPG = 0;		
		}		
		$vlLib = $SDV2Q;
		$JAC = ($SDV2Q - $vlLiberado);
	//PAGAMENTO MENSAL DE PRINCIPAL E JUROS
	}elseif($tc < $i && $i <= $tt){
		//1Q
		$J1Q = round(((pow((1 + $tx), ($d1qs / 252)) - 1) * $SDV2Q),8);		
		$JPG = $J2Q + $J1Q;
		$prest = round(($vlLiberado / ($tt - ($i - 1))), 8);
		$SDV1Q = ($SDV2Q + $J1Q - $JPG - $prest);
					
		//2Q
		$J2Q = round(((pow((1 + $tx), ($d2qs / 252)) - 1) * $SDV1Q),8);
		$SDV2Q = $SDV1Q + $J2Q;				
		
		$vlLib = $SDV2Q;
		
		$vlLiberado = $vlLiberado - $prest;
	}
	
	$IVM = 0;	
	$JAC = ($SDVFN - $vlLib);	
	$APFNA = 0;	
	$J0Q = $SDV1Q - $vlLib;	
?>