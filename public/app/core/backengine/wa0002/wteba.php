<?
session_start();
// Sanitização de subdomínios
include_once($_SERVER['DOCUMENT_ROOT'] . '/sanitize.php');
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
if (isset($_POST['vr'])) {
	
	// Decodifica o JSON recebido
	$vr = json_decode($_POST['vr'], true);
	// Verifica se a decodificação foi bem-sucedida
	if ($vr === null) {
		die("<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Dados inválidos enviados.</p>");
	}
	$tx = ($vr['tx']);
	
	if(!empty($_GET['v'])){
		// Inclui a função de edição
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/update.php';		
		//Verifica se o subcrédito existe
		$sbcr = search('app', 'wa0002_data_alterado', '', "id = {$_GET['v']}");
		if(count($sbcr) > 0){
			//Monta os  valores a serem alterados
			$values = "sc = '{$vr['sc']}', ct = '{$vr['ct']}', nl = '{$vr['nl']}', sb = '{$vr['sb']}', um = '{$vr['um']}', lb = '{$vr['lb']}', pr = '{$vr['pr']}',tx = '{$tx}', l0 = '{$vr['l0']}', j0 = '{$vr['j0']}', ad = '{$_SESSION['wz']}'";		
			//Tenta atualizar os dados
			if(update('app', 'wa0002_data_alterado', $values, "id = '{$_GET['v']}'")) {
				echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Subcrédito editado com sucesso.</p>";
			}else{
				echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Não foi possível editar o subcrédito.</p>";
			}	
		}		
	}else{
		// Inclui a função de inserção
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';	
		// Monta as colunas e os valores para a inserção
		$columns = 'sc,ct,nl,sb,um,lb,pr,tx,l0,j0,ad';
		$values = "'{$vr['sc']}','{$vr['ct']}','{$vr['nl']}','{$vr['sb']}','{$vr['um']}','{$vr['lb']}','{$vr['pr']}','{$tx}','{$vr['l0']}','{$vr['j0']}','{$_SESSION['wz']}'";
		// Tenta realizar a inserção e mostra mensagens de sucesso ou erro
		if (insert('app', 'wa0002_data_alterado', $columns, $values)) {
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Subcrédito incluído com sucesso.</p>";
		} else {
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Não foi possível incluir o subcrédito.</p>";
		}	
	}    
}
if(isset($_GET['del']) && !empty($_GET['del'])){
	if(count(search('app', 'wa0002_data_alterado', 'id', "id = {$_GET['del']}")) > 0){
		// Inclui a função de exclusão
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php';
		if(del('app', 'wa0002_data_alterado', "id = {$_GET['del']}")){
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Subcrédito excluído com sucesso.</p>";
		}else{
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Não foi possível excluir o subcrédito.</p>";
		}
	}
}


$soc = $_GET['sc'];
$pn = $_GET['pg'];

$office_id = $_GET['vr'];

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

if($_GET['pg'] > 0){

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
			<h4 class="fs-c uppercase cm-pad-5-t">Subcréditos</a></h4>
			<div class="position-absolute abs-r-0 abs-t-0 w-nav">							
				<div onclick="tableToExcel('myTable', 'BNDES-Subcréditos')" title="Exportar para Excel" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
					<i class="far fa-file-excel"></i>
				</div>				
				<?php
				if($_GET['v'] == 'a'){
				?>
				<div onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=1&sc=<?php echo $soc; ?>&v', '<?php echo $office_id; ?>');" title="Ver menos" class="cm-mg-5-h w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">												
					<i class="fas fa-minus"></i>
				</div>
				<?php
				}else{
				?>				
				<div onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=1&sc=<?php echo $soc; ?>&v=a', '<?php echo $office_id; ?>');" title="Ver todos" class="cm-mg-5-h w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
					<i class="fas fa-plus"></i>
				</div>				
				<?php
				}
				?>
				
				<div onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=0&sc&v', '<?php echo $office_id; ?>');" title="Adicionar contrato" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
					<i class="fas fa-plus-circle"></i>
				</div>
				
				<div class="clear"></div>
			</div>
			<div class="w-nav-form cm-mg-10-t float-right">
				<select class="float-right border-like-input w-rounded-5 border-none uppercase fs-b cm-pad-5" onchange="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=1&sc=' + document.getElementById('socSelect').value + '&v', '<?php echo $office_id; ?>');" id="socSelect">								
					<?php
					foreach($SLib as $unds){
						$tt = search('cmp', 'companies', 'tt', "id = '{$unds}'")[0]['tt'];
						if($soc == $unds){						
						?>
						<option selected value="<?php echo $unds; ?>"><?php echo $tt; ?></option>
						<?php
						}else{
						?>
						<option value="<?php echo $unds; ?>"><?php echo $tt; ?></option>
						<?php
						}
					}
					?>
				</select>					
			</div>	
			<div style="clear: both"></div>
		</div>
		<?php		
		if($WL1_count == 0){
			?>
			<div class="cm-mg-10-t cm-mg-0-h cm-pad-10 w-rounded-5 background-gray"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>A empresa não possui empréstimos BNDES.</div>
			<?php
		}else{
			
			$a_sc = array();
			$a_ct = array();
			$a_nl = array();
			$a_sb = array();
			$a_lb = array();
			$a_tx = array();
			$a_l0 = array();
			$a_jo = array();
			$a_um = array();
			$a_ad = array();
											
			foreach($BLib[0] as $libn){				
				$a_id[] = $libn['id'];
				$a_sc[] = $libn['sc'];
				$a_ct[] = $libn['ct'];
				$a_nl[] = $libn['nl'];
				$a_sb[] = $libn['sb'];
				$a_lb[] = $libn['lb'];
				$a_tx[] = $libn['tx'];
				$a_l0[] = $libn['l0'];
				$a_j0[] = $libn['j0'];
				$a_um[] = $libn['um'];
				$a_ad[] = $libn['ad'];
				$a_pr[] = $libn['pr'];				
			}
			
			echo array_search(5,$BLib);
		
		?>
		<div class="table-responsive background-gray w-rounded-10 cm-mg-10-t cm-mg-15-b cm-pad-20">
			<table id="myTable" class="table large-12 medium-12 small-12" id="WTBA">
				<thead>
					<tr>
						<th class="cm-pad-5-b large-2 medium-2 small-4">Contrato</th>						
						<th class="cm-pad-5-b large-2 medium-2 small-4" title="Valor em Moeda Contratual">Valor</th>
						<th class="cm-pad-5-b large-2 medium-2 hide-for-small-only">Spread</th>						
						<th class="cm-pad-5-b large-2 medium-2 hide-for-small-only">Vencimento</th>					
						<th class="cm-pad-5-b large-2 medium-2 small-4" title="Moeda Contratual">Moeda</th>						
						<th style="display: none;">Subcrédito</th>
						<th style="display: none;">Data Liberação</th>
						<th style="display: none;">Prestações de Principal</th>
					</tr>
				</thead>
				<tbody class="text-center fs-c">
				<?php
				for($i=$ka;$i<=$kb && $i >= 0;$i++){
					if(array_key_exists($i, $a_sc)){
					?>
					<tr class="">
						<td class="large-2 medium-2 small-4 cm-pad-5"><?php echo $a_ct[$i].'/'.$a_nl[$i]; ?></td>						
						<td class="large-2 medium-2 small-4 cm-pad-5"><?php echo number_format($a_lb[$i],4,",","."); ?></td>
						<td class="large-2 medium-2 hide-for-small-only cm-pad-5"><?php echo number_format(($a_tx[$i] * 100),2,",",".").'%'; ?></td>						
						<td class="large-2 medium-2 hide-for-small-only cm-pad-5"><?php echo date('d/m/Y', strtotime($a_j0[$i])); ?></td>						
						<td class="large-2 medium-2 small-4 cm-pad-5">
						<?php 
						if($a_um[$i] == 1){
							echo 'URTJLP 360';
						}elseif($a_um[$i] == 2){
							echo 'UMIPCA';
						}elseif($a_um[$i] == 3){
							echo 'URTJLP 365';
						}elseif($a_um[$i] == 4){
							echo 'TLP';
						}
						?>
						</td>
						
						<td style="display: none;"><?php echo $a_sb[$i]; ?></td>
						<td style="display: none;"><?php echo date('d/m/Y', strtotime($a_l0[$i])); ?></td>
						<td style="display: none;"><?php echo ($a_pr[$i] + 1); ?></td>
						<td class="text-center">
							<div class="">
							<div onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=0&sc=<?php echo $a_sc[$i]; ?>&v=<?php echo $a_id[$i]; ?>', '<?php echo $office_id; ?>');" title="Editar subcrédito" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
								<i class="fas fa-edit"></i>
							</div>
							<div onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=<?php echo $np; ?>&sc=<?php echo $soc; ?>&v&del=<?php echo $a_id[$i]; ?>', '<?php echo $office_id; ?>');" title="Ver menos" class="cm-mg-5-h w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">												
								<i class="fas fa-trash"></i>
							</div>						
							</div>
						</td>
					</tr>					
					<?php
					}
				}
				?>
				</tbody>
			</table>
		</div>
		<div class="btn-group w-btn-group" role="group">
			<?php
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
			<button style="height: 25px; width: 25px;" <?php if($pn == 1){ echo 'disabled'; } ?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=1&sc=<?php echo $soc; ?>&v', '<?php echo $office_id; ?>');"><i class="fas fa-chevron-left"></i></button>
			<?php
			for($a=$w;$a<=$z && $a > 0;$a++){
			if($pn == $a){
			?>		
			<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" disabled><?php echo $a; ?></button>
			<?php
			}else{
			?>
			<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=<?php echo $a; ?>&sc=<?php echo $soc; ?>&v', '<?php echo $office_id; ?>');"><?php echo $a; ?></button>		
			<?php
			}
			}
			?>
			<button style="height: 25px; width: 25px;" <?php if($pn == $np){ echo 'disabled'; }?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=<?php echo $np; ?>&sc=<?php echo $soc; ?>&v', '<?php echo $office_id; ?>');"><i class="fas fa-chevron-right"></i></button>
		</div>
		<?php
		}
		?>
    </div>	
</div>
<?php
}else{	
$editMode = false;
if(!empty($_GET['sc']) && !empty($_GET['v'])){	
	$sbcr = search('app', 'wa0002_data_alterado', '', "id = {$_GET['v']} AND sc = {$_GET['sc']}");
	if(count($sbcr) > 0){
		$editMode = true;
	}
}
$moedas = [
	'URTJLP (360 dias)' => 1,
	'URTJLP (365 dias)' => 2,
	'UMIPCA (252 dias)' => 3,
	'IPCA TLP (252 dias)' => 4
	];
?>
<div class="card position-relative">
	<div class="card-body">
		<div class="w-page-header">
			<h4 class="fs-c uppercase cm-pad-5-t"><?php if($editMode === true){ echo 'Editar '.$sbcr[0]['ct'].'/'.$sbcr[0]['nl']; }else{ ?> Novo Subcrédito <?php } ?></a></h4>
			<div class="position-absolute abs-r-0 abs-t-0 w-nav">							
				<div onclick="tableToExcel('myTable', 'BNDES-<?php echo $APDT; ?>')" title="Exportar para Excel" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
					<i class="far fa-file-excel"></i>
				</div>
				<div class="clear"></div>
			</div>						
		</div>			
		<div id="newSub" class="w-form cm-mg-30-t">						
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Empresa</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<select id="sc" name="sc" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10" <?php if($editMode === true){ ?> disabled <?php } ?>>
						<?php if($editMode == false){ ?> 
						<option value="" selected disabled>Selecione uma empresa</option>
						<?php } ?>
						<?php
						foreach($SLib as $unds){
						$tt = search('cmp', 'companies', 'tt', "id = '{$unds}'")[0]['tt'];							
						?>
						<option value="<?php echo $unds; ?>" <?php if($editMode == true && $sbcr[0]['sc'] == $unds){ ?> selected <?php } ?>><?php echo $tt; ?></option>
						<?php							
						}
						?>												
					</select>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Subcrédito</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input name="ct" style="width: 70%" type="text" class="float-left input-border border-like-input required cm-pad-10 w-rounded-5" oninput="this.value = this.value.replace(/\D/g, '').replace(/(\d{2})(\d{1,3})?(\d{1,3})?/, (match, p1, p2, p3) => [p1, p2, p3].filter(Boolean).join('.'))" maxlength="10" placeholder="XX.XXX.XXX" <?php if($editMode === true){ ?> disabled value="<?php echo $sbcr[0]['ct']; ?>" <?php } ?>>
					<input name="nl" style="width: calc(30% - 10px)" class="cm-mg-10-l float-left input-border border-like-input required cm-pad-10 w-rounded-5" type="text" placeholder="XXX" maxlength="3" <?php if($editMode === true){ ?> disabled value="<?php echo $sbcr[0]['nl']; ?>" <?php } ?>></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Identificador</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input name="sb" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10" placeholder="Ex.: A1" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['sb']; ?>" <?php } ?>></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Unidade Monetária</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<select name="um" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10">
						<option value="" selected disabled>Selecione a moeda contratual</option>
						<?php 
						foreach($moedas as $key => $value){
							if($value == $sbcr[0]['um']){
								echo '<option selected value="'.$value.'">'.$key.'</option>';
							}else{
								echo '<option value="'.$value.'">'.$key.'</option>';
							}							
						}
						?>						
					</select>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b" title="Trata-se do valor liberado, descontado da comissão, antes de conversão em R$.">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Liberação Líquida em U.M.</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input name="lb" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10" placeholder="Ex.: 1000000,0000" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['lb']; ?>" <?php } ?>></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Amortizações</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right" title="Spread ou Taxa Fixa Equivalente. TEq = {[1 + (T1/100)] x [1 + (T2/100)] x ... x [1 + (Tn/100)]} - 1">
					<input name="pr" style="width: calc(50% - 5px)" type="text" name="office_pass" id="office_pass" class="float-left input-border border-like-input required cm-pad-10 w-rounded-5" placeholder="Nº de Amortizações" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['pr']; ?>" <?php } ?>></input>
					<input name="tx" style="width: calc(50% - 5px)" class="cm-mg-10-l float-left input-border border-like-input required cm-pad-10 w-rounded-5" type="text" name="office_id" id="office_id" placeholder="Custo Financeiro Equivalente" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['tx']; ?>" <?php } ?> ></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Início</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input name="l0" type="date" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['l0']; ?>" <?php } ?>></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label>Fim</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input name="j0" type="date" class="w-rounded-5 input-border border-like-input required large-12 medium-12 small-12 cm-pad-10" <?php if($editMode === true){ ?> value="<?php echo $sbcr[0]['j0']; ?>" <?php } ?>></input>
				</div>
				<div class="clear"></div>
			</div>							
		</div>
		<?php
		if($editMode === true){
		?>
		<div onclick="formValidator2('newSub', 'core/backengine/wa0002/wteba.php?qt&pg=1&sc=' + document.getElementById('sc').value + '&v=<?php echo $_GET['v']; ?>&vr=<?php echo $office_id; ?>', 'wteba')" class="w-rounded-5 large-3 medium-3 small-12 pointer border-none w-bkg-tr-gray cm-pad-5 text-center float-right" onclick="" title="Incluir Liberação">Confirmar Edição</div>
		<?php	
		}else{
		?>
		<div onclick="formValidator2('newSub', 'core/backengine/wa0002/wteba.php?qt&pg=1&sc=' + document.getElementById('sc').value + '&v&vr=<?php echo $office_id; ?>', 'wteba')" class="w-rounded-5 large-3 medium-3 small-12 pointer border-none w-bkg-tr-gray cm-pad-5 text-center float-right" onclick="" title="Incluir Liberação">Incluir</div>
		<?php	
		}
		?>		
		<div class="clear"></div>
	</div>
</div>
<?php
}
?>