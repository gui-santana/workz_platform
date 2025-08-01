<?
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();
// Sanitização de subdomínios
include_once($_SERVER['DOCUMENT_ROOT'] . '/sanitize.php');
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
header('Content-type: text/html; charset=utf-8');
setlocale( LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');
date_default_timezone_set('America/Sao_Paulo');

if(isset($_POST['vr']) && !empty($_POST['vr'])){
	$vr = explode('|', $_POST['vr']);
	$sctt = search('cmp', 'companies', 'tt', "id = '{$vr[0]}'");
	if(count($sctt) > 0){
		// Inclui a função de inserção
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';	
		// Monta as colunas e os valores para a inserção
		$dtch = date('Y-m-t', strtotime($vr[1]));
		$columns = 'scid,ctid,lgtp,lgds,vlch,dtch,lgus';
		$values = "{$vr[0]},'0','0','Registro de apuração','{$vr[2]}','{$dtch}',{$_SESSION['wz']}";	

		// Tenta realizar a inserção e mostra mensagens de sucesso ou erro		
		if (insert('app', 'wa0002_regs_alterado', $columns, $values)) {
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Apuração de ".$sctt[0]['tt']." registrada com sucesso.</p>";
		} else {
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Não foi possível registrar a apuração.</p>";
		}
	}
}

$office_id = $_GET['vr'];

$soc = $_GET['sc'];
$pn = $_GET['pg'];
if($_GET['PRAP'] <> ''){
	//ACESSO VIA GOTO
	$APDT = $_GET['PRAP'];
	//OBSOLETO ATUALIZAÇÃO 06062019
	//$PRAP = date($_GET['PRAP']."-15");
	$PRAP = date('Y-m-15', strtotime($_GET['PRAP']));
}else{
	//ACESSO AUTOMÁTICO
	$APDT = date("Y-m");
	$PRAP = date("Y-m-15");
}
$lday = date('Y-m-t', strtotime($PRAP)); //Último dia do mês de apuração

//Company Group
$SLib = array_column(search('cmp', 'companies_groups', 'emC', "emP = '{$office_id}'"),'emC');
if(!empty($_GET['sc'])){
	$soc = $_GET['sc'];
}else{
	$soc = $SLib[0];
}
$BLib = array();
$BLib[] = search('app', 'wa0002_data_alterado', '', "sc = '{$soc}'");
$WL1_count = count($BLib[0]);

require_once($_SERVER['DOCUMENT_ROOT'].'/app/core/backengine/wa0002/WF11A_teste.php');

if($_GET['v'] == 'a'){
	$kb = $WL1_count;
	$ka = 0;
	$np = 1;
}else{
	$kb = ($pn * 20) - 1;
	$ka = ($kb - 19);
	$np = ceil($WL1_count / 20); //Número de paginas
}
?>
<script type="text/javascript">
	var tableToExcel = (function(){
		var uri = 'data:application/vnd.ms-excel;base64,'
		, template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>'
		, base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))) }
		, format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }) }
		return function(table, name) {
			if (!table.nodeType) table = document.getElementById(table)
			var ctx = {worksheet: name || 'Worksheet', table: table.innerHTML}
			window.location.href = uri + base64(format(template, ctx))
		}
	})()
</script>
<div class="card position-relative">
	<div class="card-body">
		<div class="w-page-header">
			<h4 class="fs-c uppercase cm-pad-5-t">Movimentação | <? echo strftime('%B/%Y', strtotime($lday));?></a></h4>
			<div class="position-absolute abs-r-0 abs-t-0 w-nav">
				
				<div onclick="tableToExcel('myTable', 'BNDES-<? echo $APDT; ?>')" title="Exportar para Excel" title="Exportar para Excel" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">
					<i class="far fa-file-excel"></i>
				</div>			
				<?
				if($_GET['v'] == 'a'){
				?>
				<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime($PRAP)); ?>&v&l', '<? echo $office_id; ?>');" title="Comprimir" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">									
					<i class="fas fa-compress"></i>
				</div>
				<?
				}else{
				?>
				<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime($PRAP)); ?>&v=a&l', '<? echo $office_id; ?>');" title="Expandir" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">										
					<i class="fas fa-expand"></i>
				</div>
				<?
				}
				if($_GET['v'] == 'a' && $_GET['l'] <> 'a'){
				?>												
				<div onclick="goPost('core/backengine/wa0002/wtebb.php?pg=<? echo $pn; ?>&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime($PRAP)); ?>&v&l&vr=<? echo $office_id; ?>','wteba',document.getElementById('wteb_reg').value,'')" title="Registrar" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">					
					<i class="fas fa-check-double"></i>
				</div>
				<?
				}else{
					if($_GET['l'] == 'a'){
					?>
					<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime($PRAP)); ?>&v&l', '<? echo $office_id; ?>');" title="Alternar para Apuração Mensal (R$)" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">												
						<i class="fas fa-undo"></i>
					</div>
					<?
					}else{
					?>
					<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime($PRAP)); ?>&v=a&l=a', '<? echo $office_id; ?>');" title="Alternar para Listagem de Cadastro (UM)" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">						
						<i class="fas fa-redo"></i>
					</div>
					<?
					}
				}								
				?>
				<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime('-1 day', strtotime(date('Y-m-01', strtotime($PRAP))))); ?>&v&l', '<? echo $office_id; ?>');" title="Mês anterior" class="cm-mg-15-l cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left">					
					<i class="fas fa-chevron-left"></i>
				</div>				
				<div onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo date('Y-m', strtotime('+1 day', strtotime($lday))); ?>&v&l', '<? echo $office_id; ?>');" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 float-left" title="Próximo mês">					 
					<i class="fas fa-chevron-right"></i>
				</div>			
				
				<div class="clear"></div>
			</div>			
			<div class="large-12 medium-12 small-12 w-nav-form cm-mg-10-t ">			
				<input onchange="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=<? echo $pn; ?>&sc=<? echo $soc; ?>&PRAP=' + this.value + '&v&l', '<? echo $office_id; ?>');" class="w-rounded-5 form-control border-like-input float-left" style="padding: 3px;" type="month" name="PRAP" id="PRAP" value="<? echo $APDT; ?>"/>								
				<select class="float-right border-like-input w-rounded-5 border-none uppercase fs-b cm-pad-5" onchange="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=' + document.getElementById('socSelect').value + '&PRAP=<? echo $APDT; ?>&v&l', '<? echo $office_id; ?>');" id="socSelect">
					<?
					foreach($SLib as $unds){
						$tt = search('cmp', 'companies', 'tt', "id = '{$unds}'")[0]['tt'];
						if($soc == $unds){						
						?>
						<option selected value="<? echo $unds; ?>"><? echo $tt; ?></option>
						<?
						}else{
						?>
						<option value="<? echo $unds; ?>"><? echo $tt; ?></option>
						<?
						}
					}
					?>
				</select>							
				<div style="clear: both"></div>
			</div>			
		</div>
	
<?
if($WL1_count == 0){
	?>	
	<div class="cm-mg-10-t cm-pad-10 w-rounded-5 background-gray"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>A empresa não possui empréstimos BNDES.</div>
	<?
}else{
	$usnm = 'SYSTEM';
	$dtnw = date('Y-m-d H:i:s');
	
	$a_id = array();
	$a_sc = array();
	$a_sb = array();
	$a_ct = array();
	$a_nl = array();
	$a_lb = array();
	$a_tx = array();
	$a_l0 = array();
	$a_j0 = array();
	$a_um = array();
	$a_ad = array();
	$a_pr = array();
	$a_du = array();
			
	foreach($BLib[0] as $libn){
		$a_id[] = $libn['id'];
		$a_sc[] = $libn['sc'];
		$a_sb[] = $libn['sb'];
		$a_ct[] = $libn['ct'];
		$a_nl[] = $libn['nl'];
		$a_lb[] = $libn['lb'];
		$a_tx[] = $libn['tx'];
		$a_l0[] = $libn['l0'];
		$a_j0[] = $libn['j0'];
		$a_um[] = $libn['um'];
		$a_ad[] = $libn['ad'];
		$a_pr[] = $libn['pr'];
		$a_du[] = $libn['du'];
	}

	//BUSCA MOEDAS CONTRATUAIS POR DATA
	function Currencies($um, $dtPrest){
				
		if($um == 1){
			$scurr = search('app', 'wa0013_URTJLP', '', "Data = '{$dtPrest}'");
			if(count($scurr) > 0){
				$vcurr = $scurr[0];			
			}else{
				$vcurr = search('app', 'wa0013_URTJLP', '', "id > 0 ORDER BY ID DESC LIMIT 1")[0];
			}	
		}elseif($um == 2){
			$scurr = search('app', 'wa0013_UMIPCA', '', "Data = '{$dtPrest}'");
			if(count($scurr) > 0){
				$vcurr = $scurr[0];			
			}else{
				$vcurr = search('app', 'wa0013_UMIPCA', '', "id > 0 ORDER BY ID DESC LIMIT 1")[0];
			}
		}elseif($um == 3){
			$scurr = search('app', 'wa0013_URTJLP_360_365', '', "Data = '{$dtPrest}'");
			if(count($scurr) > 0){
				$vcurr = $scurr[0];			
			}else{
				$vcurr = search('app', 'wa0013_URTJLP_360_365', '', "id > 0 ORDER BY ID DESC LIMIT 1")[0];
			}
		}elseif ($um == 4) {
            // IPCA + TLP
            $where  = "Data <= '{$dtPrest}' ORDER BY Data DESC LIMIT 1";
            $scurr  = search('app', 'wa0013_IPCA_TLP', 'Valor,Data', $where);
            if (count($scurr) > 0) {
                $vcurr = $scurr[0];
            } else {
                // fallback: caso não haja registro anterior, pega o mais recente da tabela
                $vcurr = search('app', 'wa0013_IPCA_TLP', 'Valor,Data', 
                                "ORDER BY Data DESC LIMIT 1")[0];
            }
            $currency = str_replace(",",".",$vcurr['Valor']);
            $lastdt   = $vcurr['Data'];
            return [$currency, $lastdt];
        }
		$currency = str_replace(",",".",$vcurr['Valor']);
		$lastdt = $vcurr['Data'];
		
		$currency_info = array($currency, $lastdt);
		return $currency_info;
	}

	//Checa se o dia buscado é feriado
	function ChkFeriado($dtC){
		$ckf = search('app', 'wa0002_frds', 'st', "dt = '{$dtC}'");
		$rsf = $ckf[0];
		$stf = $rsf['st'];		
		return $stf;
	}

	//REGISTRO DE APURAÇÃO -- ALTERAR PARA ENVIO JS
	foreach($SLib as $socc){
		$scic = $socc;
		if(isset($_POST['PGPR_'.$scic])){			
			include $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';			
			$reg = insert('app', 'wa0002_regs_alterado', 'scid,ctid,lgtp,lgdt,lgds,vlch,dtch,lgus', "{$_POST['SCAP_'.$scic]},'0','0',{$dtnw},{$lgds},{$vlch},{$_POST['DTAP_'.$scic]},{$usnm}");
		}
	}
	?>
	<div class="table-responsive background-gray w-rounded-10 cm-mg-10-t cm-mg-15-b cm-pad-20 overflow-auto">
	<table id="myTable" class="table large-12 medium-12 small-12">
		<thead>
			<?
			if($_GET['l'] == ''){
			?>
			<tr>				
				<th class="cm-pad-5-b large-2 medium-2 small-4 text-ellipsis" title="Contrato de Liberação">Subcrédito</th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Principal a Pagar">Principal <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Juros a Pagar">Juros <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Juros Competência">Juros Compt. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Juros Acumulados durante a Primeira Quinzena do Exercício">Juros 1ª Q. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Juros Acumulados durante a Segunda Quinzena do Exercício">Juros 2ª Q. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Variação Monetária, em relação ao período anterior">Var. Mon. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Saldo do Principal, em Curto Prazo">Saldo C.P. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Saldo do Principal, em Longo Prazo">Saldo L.P. <a class="fs-b">(R$)</a></th>
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Transferência entre Longo e Curto prazos">Transf. <a class="fs-b">(R$)</a></th>				
				<th class="cm-pad-5-b cm-pad-5-l large-2 medium-2 small-4 text-ellipsis" title="Saldo do Principal Remanescente">Saldo Princ. <a class="fs-b">(R$)</a></th>				
			</tr>
			<?
			}elseif($_GET['l'] == 'a'){
			?>
			<tr>				
				<th class="cm-pad-5-b large-2 medium-2 small-4 text-ellipsis" title="Contrato de Liberação">Subcrédito</th>
				<th class="cm-pad-5-b large-2 medium-2 small-4 text-ellipsis" title="Período">Período</th>
				<th class="cm-pad-5-b large-2 medium-2 small-4 text-ellipsis hide-for-small-only" title="Parcela de Principal, em Unidade Monetária">Principal <a class="fs-b" title="em Unidade Monetária"> (U.M.)</a></th>
				<th class="cm-pad-5-b large-2 medium-2 small-4 text-ellipsis hide-for-small-only" title="Parcela de Juros, em Unidade Monetária">Juros <a class="fs-b" title="em Unidade Monetária"> (U.M.)</a></th>
				<th class="text-right display-none" title="Juros Acumulados durante a Primeira Quinzena do Exercício">Juros 1ª Q <a class="fs-b" title="em Unidade Monetária"> (U.M.)</a></th>
				<th class="text-right display-none" title="Juros Acumulados durante a Segunda Quinzena do Exercício">Juros 2ª Q. <a class="fs-b" title="em Unidade Monetária"> (U.M.)</a></th>
				<th class="cm-pad-15-b large-2 medium-2 small-4 text-ellipsis" title="Saldo de Principal, em Unidade Monetária">Saldo <a class="fs-b" title="em Unidade Monetária"> (U.M.)</a></th>
			</tr>
			<?
			}
			?>
		</thead>
		<tbody class="text-center fs-c">
		<?
		
		$tx_tjlp = search('app', 'wa0013_TJLP', 'Valor,Data', '');
		$txvl = array_column($tx_tjlp, 'Valor');
		$txdt = array_column($tx_tjlp, 'Data');
		
	$PRINC = array();
	$JUROS = array();
	$JURAC = array();
	$VARIA = array();
	$SALCP = array();
	$SALLP = array();
	$TRANP = array();	
	$UNMON = array(); //Unidade Monetária
	$UM15D = array();
	$UM30D = array();
	$CONTR = array();
	
	for($i_page=$ka;$i_page<=$kb && $i_page >= 0;$i_page++){
		if(array_key_exists($i_page, $a_sc)){
			
			$sk = $a_sc[$i_page];
			$sb = $a_sb[$i_page][0];
			$um = $a_um[$i_page];
			$pr = (-$a_pr[$i_page]);
			$l0 = date('Y-m-15',strtotime($a_l0[$i_page]));
			$dx = date('d', strtotime($a_l0[$i_page]));
			$dun = $a_du[$i_page];
			
			
			$CONTR[] = $a_ct[$i_page];
			$UNMON[] = $um;
			
			if($PRAP > date('Y-m-01', strtotime($a_l0[$i_page]))){
				
				//Aqui ele verifica se há registro de cálculo no mês anterior.
				//Caso não haja registro, o sistema segrega as informações contratuais.
				$rgdt = date('Y-m-15', strtotime('-1 month', strtotime($PRAP)));    
			
				if($um == 2){
					$pr = ($pr * 12);
				}
				
				// REGISTROS DE ALTERAÇÃO
				
				$valts = array();
				$dtchs = array();
				$allvalts = array();				
				$salt = search('app', 'wa0002_regs_alterado', '', "ctid = '{$a_id[$i_page]}' AND lgtp = 4 ORDER BY dtch DESC");
				foreach($salt as $valt){
					if($valt['dtch'] <= $lday){
						//Cria um array de alterações que ocorreram antes do fim do mês de apuração										
						$valts[] = $valt['vlch'];
						$dtchs[] = $valt['dtch'];
						$allvalts[] = $a_id[$i_page].' - '.$valt['vlch'].' - '.$valt['dtch'].' <= '.$lday.'<br>';				
					}
				}
				
				//Assume a alteração mais recente, em relação ao fim do mês de apuração
				if(!empty($valts)){
					$valt = $valts[0];
					$dtch = $dtchs[0];					
				}else{
					$valt = '';
					$dtch = '';
				}							
								
				if($valt <> ''){
					$altdt = $dtch;

					$reginfo = explode(';',$valt);
					$altvl = str_replace(',','.',$reginfo[0]);
					if(array_key_exists(1,$reginfo)){
						$alttx = (str_replace(',','.',$reginfo[1])/100);
					}else{
						$alttx = "";
					}

				}else{
					$altdt = "";
					$altvl = "";
					$alttx = "";
				}
						

				//Encontra a menor data, por subcrédito
				
				$bndes_query = search('app', 'wa0002_data_alterado', '', "sb LIKE '%{$sb}%' AND sc = '{$sk}' AND um = '{$um}' ORDER BY l0 DESC");
				
				foreach($bndes_query as $row);
				//Data da primeira amortização do subcrédito - BUSCAR NA BASE DE DADOS
				$am = date('Y-m-15', strtotime("$pr months", strtotime($row['j0'])));
				$ll = date('Y-m-15',strtotime($row['l0']));
				
				//(ll) É (l0) DA LIBERAÇÃO MAIS ANTIGA DO SUBCRÉDITO (PRIM. LIBERAÇÃO DO SUBCRÉDITO)
				
				$ma = date('m', strtotime($am));
				
				//Data Inicial das Prestações
				if($altdt <> ""){
					$indt = $altdt;
					$ll = $indt;
					$l0 = $indt;
				}else{
					$indt = $a_l0[$i_page];
				}

				if($indt > $am){
					$di = date('Y-m-15', strtotime('+1 months', strtotime($indt)));
				}else{
					$di = $am;
				}
				
				if($l0 > $am || $altdt <> ""){
					$tc = 0;
				}else{
					$ct = (new DateTime($l0))->diff(new DateTime($am));
					$ct_a = $ct->format('%Y')*12;
					$ct_m = $ct->format('%m');
					$tc = ($ct_a + $ct_m);
				}

				//Distancia entre a primeira liberação
				$lt = (new DateTime($ll))->diff(new DateTime($am));
				$lt_a = $lt->format('%Y')*12;
				$lt_m = $lt->format('%m');
				$tl = ($lt_a + $lt_m + 1);

				//Prestações Totais
				$pt = (new DateTime($di))->diff(new DateTime($a_j0[$i_page]));
				$pt_a = $pt->format('%Y')*12;
				$pt_m = $pt->format('%m');
				$tm = ($pt_a + $pt_m + 1);


				//Se a data de liberação for posterior à data inicial da amortização, então ele soma um mês ao período. Dessa forma, o sistema utiliza o mês anterior como base de cálculo do saldo de juros acumulados.
				if($l0 > $am || $altdt <> ""){
					$tm = $tm + 1; 
				}
				
				$tt = $tc + $tm;

				// ! Cálculo de principais a vencer !

				//Valor Liberado
				if($altvl <> ""){
					$vlLiberado = $altvl;
				}else{
					$vlLiberado = round($a_lb[$i_page], 8);
				}
				$vlLib = $vlLiberado;
				
				//Data de Liberação
				$dl = date('15/m/Y', strtotime($l0));
				$dataExplode = explode("/",$dl);
				$dia = $dataExplode[0];
				$mes = $dataExplode[1];
				$ano = $dataExplode[2];
				//Taxa de Spread
				if($alttx <> ""){
					$txSpread = $alttx;
				}else{
					$txSpread = $a_tx[$i_page];
				}
	
				$JRPG = array();
				$JR0Q = array();
				$JR1Q = array();
				$JR2Q = array();
				$JRAC = array();
				$DTPR = array();
				$saldos = array();
				$numbers = array();
				$pv = array();
				$IVM = array();
				$vlAnterior = array();
				$n = array();
				$saldos_ipca = array();
				$jbcx = array();
				
				$teste1 = array();
				$teste2 = array();
				$teste3 = array();
				$teste4 = array();
				
				$v = 0;
				$J2Q = 0;
				$Xc = 0;
				$dac = 0;
				$jbc = 0;

				for($i=1;$i<=$tt && $i > 0;$i++){
					$lib0 = $a_l0[$i_page];
					$libx = $a_tx[$i_page];
					$libp = $a_pr[$i_page];

					$vlAnterior[] = $vlLiberado;

					$WF11 = new WF11();
					$sbrt = $WF11->WF11A($um, $dx, $altdt, $mes, $dia, $ano, $vlLiberado, $txdt, $txvl, $txSpread, $tc, $tl, $tt, $tm, $lib0, $libx, $libp, $di, $i, $v, $J2Q, $ma, $vlLib, $dun, $dac, $jbc);
						
					$JRPG[] = $sbrt[0][0];
					$JR0Q[] = $sbrt[0][8];
					$JR1Q[] = $sbrt[0][1];
					$JR2Q[] = $sbrt[0][2];
					$DTPR[] = $sbrt[0][3];
					$saldos[] = $sbrt[0][4];
					$numbers[] = $sbrt[0][5];
					$IVM[] = $sbrt[0][10];
					$n[] = $sbrt[0][6];									
					$saldos_ipca[] = $sbrt[0][11];
					
					

					$teste1[] = $sbrt[0][12];
					$teste2[] = $sbrt[0][13];
					$teste3[] = $sbrt[0][14];
					$teste4[] = $sbrt[0][15];

					$JRAC[] = $sbrt[0][18];


					//Valores pertencentes ao loop
					$dac = $sbrt[0][16];
					$vlLib = $sbrt[0][11];
					$vlLiberado = $sbrt[0][4];
					$v = $sbrt[0][7];
					$J2Q = $sbrt[0][2];
					$jbc = $sbrt[0][17];

					$jbcx[] = $jbc;
					$pv[] = $v;

					$mes = $mes + 1;
					if ($mes > 12){
						$mes = 1;
						$ano = $ano + 1;
					}
				}				
				
				//COMPETÊNCIA ATUAL
				$pk = (new DateTime(date('Y-m-d', strtotime('+1 months', strtotime($l0)))))->diff(new DateTime($PRAP));
				$pk_a = $pk->format('%Y')*12;
				$pk_m = $pk->format('%m');
				if($PRAP == $l0){
					$tk = 0;
				}else{
					$tk = ($pk_a + $pk_m + 1);
				}
				
			$PGTOP = 0;
			$PGTOJ = 0;
			$AD1QJ = 0;
			$AD2QJ = 0;
			$VARMN = 0;
			$SLDCP = 0;
			$SLDLP = 0;
			$TLPCP = 0;
			$SALDO = 0;
				
			if($tk < count($DTPR)){
				
				//DATAS FINAIS
				$DTFN = date('t/m/Y', strtotime($PRAP));
				$DTAN = date('t/m/Y', strtotime('-1 months', strtotime($PRAP)));
				
				//DIA ÚTIL P/ BUSCA DE MOEDA (BASE 256)
				if($um == 2 || $um == 4){
					$DFQZ = $PRAP;
					$DFFN = date('Y-m-t', strtotime($PRAP));
					$DFAN = date('Y-m-t', strtotime('-1 months', strtotime($PRAP)));
					$NQZ = date('N', strtotime($PRAP));
					$NFN = date('N', strtotime($DFFN));
					$NAN = date('N', strtotime($DFAN));
					//Check Finais de Semana DTQZ
					if($NQZ < 6){
						$DTQZ = $DFQZ;
					}elseif($NQZ == 6){
						$DTQZ = date('Y-m-d', strtotime('+2 day', strtotime($DFQZ)));
					}elseif($NQZ == 7){
							$DTQZ = date('Y-m-d', strtotime('+1 days', strtotime($DFQZ)));
					}
					//Output com Check Feriados DTQZ
					if(ChkFeriado($DTQZ) == 0){
						if($NQZ == 6){
							$DTQZ = date('d/m/Y', strtotime('+2 days', strtotime($DTQZ)));
						}elseif($NQZ <> 7){
							$DTQZ = date('d/m/Y', strtotime('+1 day', strtotime($DTQZ)));
						}
					}else{
						$DTQZ = date('d/m/Y', strtotime($DTQZ));
					}
					//Check Finais de Semana DTFN
					if($NFN < 6){
						$DTFN = $DFFN;
					}elseif($NFN = 6){
						$DTFN = date('Y-m-d', strtotime('-1 day', strtotime($DFFN)));
					}elseif($NFN = 7){
						$DTFN = date('Y-m-d', strtotime('-2 days', strtotime($DFFN)));
					}
					//Output com Check Feriados DTFN
					if(ChkFeriado($DTFN) == 0){
						if($NFN == 1){
							$DTFN = date('d/m/Y', strtotime('-3 days', strtotime($DTFN)));
						}elseif($NFN <> 1){
							$DTFN = date('d/m/Y', strtotime('-1 day', strtotime($DTFN)));
						}
					}else{
						$DTFN = date('d/m/Y', strtotime($DTFN));
					}
					//Check Finais de Semana DTAN
					if($NAN < 6){
						$DTAN = $DFAN;
					}elseif($NAN == 6){
						$DTAN = date('Y-m-d', strtotime('-1 day', strtotime($DFAN)));
					}elseif($NAN == 7){
						$DTAN = date('Y-m-d', strtotime('-2 days', strtotime($DFAN)));
					}
					//Output com Check Feriados DTAN
					if(ChkFeriado($DTAN) == 0){
						if($NAN == 1){
							$DTAN = date('d/m/Y', strtotime('-3 days', strtotime($DTAN)));
						}elseif($NAN <> 1){
							$DTAN = date('d/m/Y', strtotime('-1 day', strtotime($DTAN)));
						}
					}else{
						$DTAN = date('d/m/Y', strtotime($DTAN));
					}

					//MOEDA ENCONTRADA
					$MCPi = Currencies($um, $DTQZ)[0];
				}
				
				//MOEDAS ENCONTRADAS
				$MFN = Currencies($um, $DTFN)[0];
				$MAN = Currencies($um, $DTAN)[0];
				$MCP = Currencies($um, $DTPR[$tk])[0];
				
				$UM15D[] = $MCP;
				$UM30D[] = $MFN;
				
				//Nº ÍNDICE P/ BUSCA DE CP E LP
				$n_a = ($n[$tk]);
				$n_b = ($n[$tk] + 11);
				
				//LISTAGEM DE PAGTOS EM CP MÊS ATUAL
				$vcp = array();
				for($i = $n_a; $i <= $n_b; $i++){
					if($i < count($numbers)){
						$vcp[] = $numbers[$i] * $MFN;
					}
				}
				//LISTAGEM DE PAGTOS EM LP MÊS ATUAL
				$vlp = array();
				for($i > $n_b; $i < $tt; $i++){
					$vlp[] = $numbers[$i] * $MFN;
				}
				//LISTA DE PAGTOS EM LP MÊS ANTERIOR
				$vla = array();
				for($i = $n_b; $i < $tt; $i++){
					$vla[] = $numbers[$i] * $MAN;
				}
				
				if($um == 2){
					$IMN = 0;
				}else{
					$IMN = ($IVM[$tk] * ($MFN - $MAN));
				}
				
				//RESULTADOS

				//01. COMPETÊNCIA
				$DATAP = $DTPR[$tk];
				
				if($um == 2 || $um == 4){
					//02. PAGAMENTO DE PRINCIPAL
					$PGTOP = $numbers[$tk] * $MCPi;
					//03. PAGAMENTO DE JUROS
					$PGTOJ = $JRPG[$tk] * $MCPi;
				}else{
					//02. PAGAMENTO DE PRINCIPAL
					$PGTOP = $numbers[$tk] * $MCP;
					//03. PAGAMENTO DE JUROS
					$PGTOJ = $JRPG[$tk] * $MCP;
				}				
				
				//04. JUROS COMPETÊNCIA DE 1Q
				if($um == 2){
					$AD1QJ = ((($IVM[$tk] + $JR1Q[$tk]) * $MCPi) - ($IVM[$tk] * $MAN));
				}elseif($um == 4){
					$AD1QJ = ($JR1Q[$tk] * $MCPi) + (($saldos_ipca[$tk - 1] - $saldos[$tk - 1]) * ($MCPi - $MAN));
				}else{
					if($n[$tk] <= $tc && $pv[$tk] == 0){
						$AD1QJ = ($PGTOJ - ($jbcx[$tk-1] * $MAN));
					}else{
						if($pv[$tk] == 0){
							$AD1QJ = abs($PGTOJ - ($JR0Q[$tk] * $MAN)) + $IMN;
						}else{
							$AD1QJ = abs($PGTOJ - ($JR1Q[$tk] * $MAN)) + $IMN;
						}
					}
				}
				//05. JUROS COMPETÊNCIA DE 2Q
				if($um == 4){			
					//JUROS ACUMULADOS NO PERÍODO DE CARÊNCIA
					if($i < $tc){
						if($JRPG[$tk] <> ''){
							//JUROS 2Q MÊS ANTERIOR + JUROS 1Q MÊS ATUAL
							$JACCR = $JACCR + ($AD2QJ + $AD1QJ);
						}else{
							$JACCR = 0;
						}
					}else{
						$JACCR = 0;
					}
					//JUROS DE 2Q MÊS ATUAL
					$AD2QJ = ($JR2Q[$tk] * $MFN) + ((($saldos_ipca[$tk] - $JR2Q[$tk]) - $saldos[$tk]) * ($MFN - $MCPi));
				}else{
					$AD2QJ = $JR2Q[$tk] * $MFN;
				}
				//06. SALDO PRINCIPAL EM CP
				$SLDCP = array_sum($vcp);
				//07. SALDO PRINCIPAL EM LP
				$SLDLP = array_sum($vlp);
				//08. SALDO DE PRINCIPAL A VENCER
				if($um == 2){
					$SALDO = $saldos_ipca[$tk] * $MFN;
				}else{
					$SALDO = $saldos[$tk] * $MFN;
				}				
				//09. VARIAÇÃO MONETÁRIA
				if($tk == 0){
					$VARMN = $SALDO - ($saldos[$tk] * Currencies($um, date('d/m/Y', strtotime($a_l0[$i_page])), $r_pdo)[0]);
				}else{
					if($um == 2){
						$VARMN = ($SALDO - ($saldos_ipca[$tk - 1] * $MAN) + $PGTOP);
					}else{
						$VARMN = ($SALDO - ($vlAnterior[$tk] * $MAN) + $PGTOP);					
					}					
				}
				//10. TRANSFERÊNCIA DE LP P/ CP
				if($um == 2 || $um == 4){
					if($PGTOP <> 0){
						$TLPCP = max((array_sum($vla) - $SLDLP) + $VARMN,0);
					}else{
						if($n[$tk] <= ($tc - 12)){
							$TLPCP = max((array_sum($vla) - $SLDLP) + $VARMN, 0);
						}else{
							$TLPCP = ((max($vlp) - max($vla)) - $VARMN);
						}						
					}					
				}else{
					//SALDO LP MÊS ANTERIOR - SALDO LP MÊS ATUAL
					//$TLPCP = array_sum($vla);
					$TLPCP = max((array_sum($vla) - $SLDLP),0);
				}
				//11. VARIAÇÃO MONETÁRIA CARÊNCIA
				if($n[$tk] <= ($tc - 12)){
					$VARMC = $VARMN;
				}else{
					$VARMC = 0;
				}
				
				if($um == 2){
					$s_princ = $saldos_ipca;
					if($PGTOJ <> 0){
						$AD1QJ = $PGTOJ - ($JRAC[($tk - 1)] * $MAN);
					}else{
						$AD1QJ = $AD1QJ + (($JRAC[$tk] * $MFN) - ($JR0Q[$tk] * $MAN) - ($AD1QJ + $AD2QJ));
					}					
				}else{
					$s_princ = $saldos;
				}
			}
			if($_GET['l'] == ''){							
			?>
			<tr>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5"><? echo $a_ct[$i_page].'/'.$a_nl[$i_page]; ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($PGTOP, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($PGTOJ, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format(($AD1QJ + $AD2QJ), 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($AD1QJ, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($AD2QJ, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($VARMN, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($SLDCP, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($SLDLP, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($TLPCP, 2, ',', '.'); ?></td>
				<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 text-right"><? echo number_format($SALDO, 2, ',', '.'); ?></td>
			</tr>
			<?				
			$PRINC[] = $PGTOP;
			$JUROS[] = $PGTOJ;
			$JURAC[] = ($AD1QJ + $AD2QJ);
			$VARIA[] = $VARMN;
			$SALCP[] = $SLDCP;
			$SALLP[] = $SLDLP;
			/*
			if($n[$tk] <= ($tc - 12)){
				$TRANP[] = $TLPCP - $VARMC;
			}else{
				$TRANP[] = $TLPCP;
			}
			*/			
			$TRANP[] = $TLPCP;
			
			}elseif($_GET['l'] == 'a'){
				
				$maxRows = max(count($DTPR), count($numbers), count($JRPG), count($saldos_ipca), count($saldos)); // Calcula o número máximo de linhas necessárias

				for ($i = 0; $i < $maxRows; $i++) { // Laço para criar linhas <tr>
					echo '<tr>';

					// Primeira coluna: Mostra o conteúdo apenas na primeira linha
					if ($i === 0) {
						echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5" rowspan="' . $maxRows . '">' . $a_ct[$i_page] . '/' . $a_nl[$i_page] . '</td>';
					}

					// Segunda coluna: Valor correspondente de $DTPR ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5" style="text-align: right;">';
					echo isset($DTPR[$i]) ? substr($DTPR[$i], 3) : '';
					echo '</td>';

					// Terceira coluna: Valor correspondente de $numbers ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 hide-for-small-only" style="text-align: right;">';
					echo isset($numbers[$i]) ? number_format($numbers[$i], 4, ",", ".") : '';
					echo '</td>';

					// Quarta coluna: Valor correspondente de $JRPG ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 hide-for-small-only" style="text-align: right;">';
					echo isset($JRPG[$i]) ? number_format($JRPG[$i], 4, ",", ".") : '';
					echo '</td>';
					
					// Quinta coluna (oculta): Valor correspondente de $JR1Q ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 display-none" style="text-align: right;">';
					echo isset($JRPG[$i]) ? number_format($JR1Q[$i], 4, ",", ".") : '';
					echo '</td>';
					
					// Sexta coluna (oculta): Valor correspondente de $JR2Q ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5 display-none" style="text-align: right;">';
					echo isset($JRPG[$i]) ? number_format($JR2Q[$i], 4, ",", ".") : '';
					echo '</td>';				
					
					// Quinta coluna: Valor correspondente condicional de $saldos ou $saldos_ipca ou vazio
					echo '<td class="large-2 medium-2 small-4 text-ellipsis cm-pad-5" style="text-align: right;">';
					if ($um == 2 || $um == 4) {
						echo isset($saldos_ipca[$i]) ? number_format($saldos_ipca[$i], 4, ",", ".") : '';
					} else {
						echo isset($saldos[$i]) ? number_format($saldos[$i], 4, ",", ".") : '';
					}
					echo '</td>';

					echo '</tr>'; // Fecha a linha atual
				}								
			}
		
			}
		}
	}
	?>
		</tbody>
	</table>
	</div>	
	</div>	
	<div>
	<?	
	$wtebb1 = number_format(array_sum($PRINC), 2, ',', '.');
	$wtebb2 = number_format(array_sum($JUROS), 2, ',', '.');
	$wtebb3 = number_format(array_sum($JURAC), 2, ',', '.');
	$wtebb4 = number_format(array_sum($VARIA), 2, ',', '.');
	$wtebb5 = number_format(array_sum($SALCP), 2, ',', '.');
	$wtebb6 = number_format(array_sum($SALLP), 2, ',', '.');
	$wtebb7 = number_format(array_sum($TRANP), 2, ',', '.');
	$UNMON = array_unique($UNMON);
	$UM15D = array_unique($UM15D);
	$UM30D = array_unique($UM30D);

	if(count($UNMON) <= count($UM15D)){
		$wtebb8 = array();
		foreach($UNMON as $key=>$value){
			if(isset($UM15D[$key], $UM30D[$key])){
				if($value == 1){
					$moec = 'URTJLP (314)';
				}elseif($value == 2){
					$moec = 'UMIPCA (184)';
				}elseif($value == 3){
					$moec = 'URTJLP (321)';
				}elseif($value == 4){
					$moec = 'TLP IPCA (185)';
				}
				$wtebb8[] = $moec.'>'.$UM15D[$key].'>'.$UM30D[$key];		
			}
		}
		$wtebb8 = implode('/', $wtebb8);
	}
	$wtebb9 = array();
	foreach(array_count_values($CONTR) as $key=>$value_b){
		$wtebb9[] = $key.'>'.$value_b;		
	}
	$wtebb9 = implode('/', $wtebb9);
	
	?>	
	<input id="wteb_reg" name="vr" type="hidden" value="<? echo $soc.'|'.$PRAP.'|'.$wtebb1.';'.$wtebb2.';'.$wtebb3.';'.$wtebb4.';'.$wtebb5.';'.$wtebb6.';'.$wtebb7.';'.$wtebb8.';'.$wtebb9; ?>"></input>
	</div>
	<div class="btn-group w-btn-group" role="group">
		<?
		if($pn <= 5){
			$w = 1;
			$z = 10;
			if($np < 10){
				$z = $np;
			}			
		}else{
			$w = ($pn - 4);
			$z = ($pn + 5);
			if($z > $np){
				$z = $np;
			}
		}
		?>
		<button style="height: 25px; width: 25px;" <?if($pn == 1){ echo 'disabled'; }?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 fs-b" onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc=<? echo $soc; ?>&PRAP=<? echo $APDT; ?>&v&l', '<? echo $_GET['vr']?>');"><i class="fas fa-chevron-left"></i></button>
		<?
		for($a=$w;$a<=$z && $a > 0;$a++){
		if($pn == $a){
		?>		
		<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 fs-b" disabled><? echo $a; ?></button>
		<?
		}else{
		?>
		<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 fs-b" onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=<? echo $a; ?>&sc=<? echo $soc; ?>&PRAP=<? echo $APDT; ?>&v&l', '<? echo $_GET['vr']?>');"><? echo $a; ?></button>				
		<?
		}
		}
		?>
		<button style="height: 25px; width: 25px;" <? if($pn == $np){ echo 'disabled'; }?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 fs-b" onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=<? echo $np; ?>&sc=<? echo $soc; ?>&PRAP=<? echo $APDT; ?>&v&l', '<? echo $_GET['vr']?>');"><i class="fas fa-chevron-right"></i></button>
	</div>
	<?
}
?>
</div>

<!-- Modal starts -->
<div class="modal fade" id="exampleModalCenter"  tabindex="-1" role="dialog" aria-labelledby="ModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="ModalLabel">Selecione um período</h5>
				<i class="settings-close mdi mdi-close" data-dismiss="modal" aria-label="Close"></i>
			</div>
			<div class="modal-body">
				<input class="form-control" type="month" name="PRAP" id="PRAP" value="<? echo $APDT; ?>"/>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Voltar</button>
				<button onclick="wtebb('<? echo $pn; ?>', '<? echo $soc; ?>', getElementById('PRAP').value, '', '')" data-dismiss="modal" type="button" class="btn btn-primary">Avançar</button>
			</div>
		</div>
	</div>
</div>
<!-- Modal Ends -->
