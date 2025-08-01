<?
	// Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
	include('../../../../sanitize.php');

	setlocale(LC_ALL, "pt_BR", "pt_BR.iso-8859-1", "pt_BR.utf-8", "portuguese");
	date_default_timezone_set('America/Sao_Paulo');

	include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
	include($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');

	$now = date('Y-m-d');

	// Função para abrir e verificar se o arquivo foi carregado corretamente
	function openFile($url) {
		$handle = fopen($url, "r");
		if (!$handle) {
			die("Erro ao abrir o arquivo: $url");
		}
		return $handle;
	}

	// HANDLE 1 - UMIPCA 184
	$handle1 = openFile("http://www.bndes.gov.br/Moedas/um184.txt");
	$b1 = [];
	while (!feof($handle1)) {
		$buffer1 = fgets($handle1, 4096);
		$b1[] = explode(";", $buffer1);
	}
	fclose($handle1);

	for ($i = 100; $i > 0; $i--) {
		if (isset($b1[$i][0]) && isset($b1[$i][1])) {
			$date1 = trim($b1[$i][0]);
			$rate1 = trim($b1[$i][1]);

			// Verifica se a data existe
			$checkdate1 = search('app', 'wa0013_UMIPCA', '', "Data = '".$date1."'");
			if (count($checkdate1) == 0) {
				// Insere Informações no BD
				$insert1 = insert('app', 'wa0013_UMIPCA', 'Data,Valor', "'".$date1."','".$rate1."'");
			}
		}
	}

	// HANDLE 2 - IPCA Acumulado 184
	$handle2 = openFile("http://www.bndes.gov.br/Moedas/um019.txt");
	$b2 = [];
	while (!feof($handle2)) {
		$buffer2 = fgets($handle2, 4096);
		$b2[] = explode(";", $buffer2);
	}
	fclose($handle2);

	if (isset($b2[1][0]) && isset($b2[1][1])) {
		$date2 = trim($b2[1][0]);
		$rate2 = trim($b2[1][1]);

		// Verifica se a data existe
		$checkdate2 = search('app', 'wa0013_IPCAAC', '', "Data = '".$date2."'");
		if (count($checkdate2) == 0) {
			// Insere Informações no BD
			$insert2 = insert('app', 'wa0013_IPCAAC', 'Data,Valor', "'".$date2."','".$rate2."'");
		}
	}

	// HANDLE 3 - TJLP
	$handle3 = openFile("http://www.bndes.gov.br/Moedas/um311.txt");
	$b3 = [];
	while (!feof($handle3)) {
		$buffer3 = fgets($handle3, 4096);
		$b3[] = explode(";", $buffer3);
	}
	fclose($handle3);

	if (isset($b3[1][0]) && isset($b3[1][1])) {
		$date3 = trim($b3[1][0]);
		$rate3 = trim($b3[1][1]);

		// Verifica se a data existe
		$checkdate3 = search('app', 'wa0013_TJLP', '', "Data = '".$date3."'");
		if (count($checkdate3) == 0) {
			// Insere Informações no BD
			$insert3 = insert('app', 'wa0013_TJLP', 'Data,Valor', "'".$date3."','".$rate3."'");
		}
	}

	// HANDLE 4 - URTJLP
	$handle4 = openFile("http://www.bndes.gov.br/Moedas/um314.txt");
	$b4 = [];
	while (!feof($handle4)) {
		$buffer4 = fgets($handle4, 4096);
		$b4[] = explode(";", $buffer4);
	}
	fclose($handle4);

	for ($i = 100; $i > 0; $i--) {
		if (isset($b4[$i][0]) && isset($b4[$i][1])) {
			$date4 = trim($b4[$i][0]);
			$rate4 = trim($b4[$i][1]);

			// Verifica se a data existe
			$checkdate4 = search('app', 'wa0013_URTJLP', '', "Data = '".$date4."'");
			if (count($checkdate4) == 0) {
				// Insere Informações no BD
				$insert4 = insert('app', 'wa0013_URTJLP', 'Data,Valor', "'".$date4."','".$rate4."'");
			}
		}
	}

	// HANDLE 5 - URTJLP 360/365
	$handle5 = openFile("http://www.bndes.gov.br/Moedas/um321.txt");
	$b5 = [];
	while (!feof($handle5)) {
		$buffer5 = fgets($handle5, 4096);
		$b5[] = explode(";", $buffer5);
	}
	fclose($handle5);

	for ($i = 100; $i > 0; $i--) {
		if (isset($b5[$i][0]) && isset($b5[$i][1])) {
			$date5 = trim($b5[$i][0]);
			$rate5 = trim($b5[$i][1]);

			// Verifica se a data existe
			$checkdate5 = search('app', 'wa0013_URTJLP_360_365', '', "Data = '".$date5."'");
			if (count($checkdate5) == 0) {
				// Insere Informações no BD
				$insert5 = insert('app', 'wa0013_URTJLP_360_365', 'Data,Valor', "'".$date5."','".$rate5."'");
			}
		}
	}

	// HANDLE 6 - CDI (Tarefa ocorre de terça a sábado)
	if (date('w') > 1) {
		$ftp_server = "ftp.cetip.com.br";
		$ftp_user = "anonymous";
		$ftp_pass = "";

		$conn_id = ftp_connect($ftp_server) or die("Couldn't connect to $ftp_server");

		$date_di = strftime("%Y%m%d", strtotime("now -1 day"));
		$date6 = date("Y-m-d", strtotime("now -1 day"));
		$myFile = "ftp://ftp.cetip.com.br/Public/".$date_di."_TAXA_DI.TXT";

		$filefound = 0;
		$attempts = 0;
		$maxAttempts = 10;

		while ($filefound == 0 && $attempts < $maxAttempts) {
			if (!file_exists($myFile)) {
				sleep(10); // Aguarda 10 segundos antes de tentar novamente
				$attempts++;
			} else {
				$filefound = 1;
				$fh = fopen($myFile, 'r');				
				$theData = number_format(fread($fh, 9) / 100, 2, ',', '');
				fclose($fh);
				$fatorDI = (1 + (pow((($theData / 100) + 1), (1 / 252)) - 1));
				$rate6A = $theData;
				$rate6B = $fatorDI;

				// Verifica se a data existe
				$checkdate6 = search('app', 'wa0013_CDI', '', "Data = '".$date6."'");
				if (count($checkdate6) == 0) {
					// Insere Informações no BD
					$insert6 = insert('app', 'wa0013_CDI', 'Data,Media,Valor', "'".$date6."','".$rate6A."','".$rate6B."'");
				}
			}
		}

		if ($filefound == 0) {
			die('Erro: Arquivo CDI não encontrado após 10 tentativas');
		}
	}

	// HANDLE 7 - TLP PRÉ
	$handle7 = openFile("http://www.bndes.gov.br/Moedas/um777.txt");
	$b7 = [];
	while (!feof($handle7)) {
		$buffer7 = fgets($handle7, 4096);
		$b7[] = explode(";", $buffer7);
	}
	fclose($handle7);

	if (isset($b7[1][0]) && isset($b7[1][1])) {
		$date7 = trim($b7[1][0]);
		$rate7 = trim($b7[1][1]);

		// Verifica se a data existe
		$checkdate7 = search('app', 'wa0013_TLP_PRE', '', "Data = '".$date7."'");
		if (count($checkdate7) == 0) {
			// Insere Informações no BD
			$insert7 = insert('app', 'wa0013_TLP_PRE', 'Data,Valor', "'".$date7."','".$rate7."'");
		}
	}

	// HANDLE 8 - IPCA TLP
	$handle8 = openFile("http://www.bndes.gov.br/Moedas/um185.txt");
	$b8 = [];
	while (!feof($handle8)) {
		$buffer8 = fgets($handle8, 4096);
		$b8[] = explode(";", $buffer8);
	}
	fclose($handle8);

	for ($i = 100; $i > 0; $i--) {
		if (isset($b8[$i][0]) && isset($b8[$i][1])) {
			$date8 = trim($b8[$i][0]);
			$rate8 = trim($b8[$i][1]);

			// Verifica se a data existe
			$checkdate8 = search('app', 'wa0013_IPCA_TLP', '', "Data = '".$date8."'");
			if (count($checkdate8) == 0) {
				// Insere Informações no BD
				$insert8 = insert('app', 'wa0013_IPCA_TLP', 'Data,Valor', "'".$date8."','".$rate8."'");
			}
		}
	}

	
	/*
	//IPCA PROJETADO 2Q ANBIMA	
	$url = 'https://www.anbima.com.br/pt_br/informar/estatisticas/precos-e-indices/projecao-de-inflacao-gp-m.htm';
	$content = file_get_contents($url);
	$first_step = explode( '<div class="card-body full padding20 padding10-xs texto-longo">' , $content );
	$second_step = explode("</div>" , $first_step[1] );
	$third_step = explode( '<div class="card-body full padding20 padding10-xs texto-longo">' , $first_step[2] );
	$fourth_step = explode("</div>" , $third_step[0] );
	$fifth_step = explode('<td height="30" style="text-align: center;"><strong>', $fourth_step[0]);

	$tx_IPCA = trim(explode('</strong>', $fifth_step[2])[0]); //Taxa IPCA segunda quinzena
	$tx_date = date('Y-m').'-15';
	$up_date = str_replace('</td>', '', explode('<td height="30" style="text-align: center;">', $fifth_step[1])[2]);

	if($up_date == date('d/m/y')){
		$checkdate9 = search('app', 'wa0013_IPCA_ANBIMA_2Q', '', "dt = '".$tx_date."'");
		if(count($checkdate9) == 0){
			$insert9 = insert('app', 'wa0013_IPCA_ANBIMA_2Q', 'dt,Valor,Data', "'".$tx_date."','".$tx_IPCA."','".date('d/m/Y')."'");
		}
	}
	*/
?>