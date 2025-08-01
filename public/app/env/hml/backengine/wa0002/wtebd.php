<?
setlocale(LC_TIME, 'pt_BR.UTF-8'); // Configura o local para português brasileiro
include_once($_SERVER['DOCUMENT_ROOT'] . '/sanitize.php'); // Sanitização de subdomínios
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
if(isset($_GET['pg'])){
	session_start();
}
if(isset($_GET['qt'])){
	$qt = explode('|', $_GET['qt']);
	$office_id = $qt[0];	
}else{
	$office_id = $_POST['vr'];
}
if(isset($_GET['del']) && !empty($_GET['del'])){
	if(count(search('app', 'wa0002_regs_alterado', 'id', "id = {$_GET['del']}")) > 0){
		// Inclui a função de exclusão
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php';
		if(del('app', 'wa0002_regs_alterado', "id = {$_GET['del']}")){
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Registro excluído com sucesso.</p>";
		}else{
			echo "<p class='cm-mg-20-b font-weight-600 fs-b'>Erro: Não foi possível excluir o registro.</p>";
		}
	}
}
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Sao_Paulo');
if(isset($_GET['vr'])){
	$dtch = date('Y-m-t', strtotime($_GET['vr']));	
}else{
	$dtch = date('Y-m-t');
}
?>
<div class="card position-relative">
	<div class="card-body">
		<div class="w-page-header">
			<h4 class="fs-c uppercase cm-pad-5-t">Resumo Movimentação | <a style="text-transform: uppercase;"><? echo utf8_encode(strftime('%B/%Y', strtotime($dtch)));?></a></h4>				
			<p class="display-none" style="font-size: 14px; float: left; text-transform: uppercase; height: 100%; padding: 7.5px 10px;"><? echo strftime('%B/%Y', strtotime($dtch));?></p>
			<div class="position-absolute abs-r-0 abs-t-0 w-nav">
				<div class="w-bkg-tr-gray w-rounded-5 cm-pad-5-h background-white float-right pointer border-like-input w-nav-button" onclick="goTo('core/backengine/wa0002/wtebd.php', 'wteba', '<? echo $office_id; ?>|1', '<? echo date('Y-m-t', strtotime('+1 day', strtotime($dtch))); ?>')" title="Próximo mês">
					<i class="fas fa-chevron-right"></i>
				</div>
				<div class="w-bkg-tr-gray w-rounded-5 cm-mg-5-h cm-pad-5-h background-white float-right pointer border-like-input w-nav-button" onclick="goTo('core/backengine/wa0002/wtebd.php', 'wteba', '<? echo $office_id; ?>|1', '<? echo date('Y-m-d', strtotime('-1 day', strtotime(date('Y-m-01', strtotime($dtch))))); ?>')" title="Mês anterior">
					<i class="fas fa-chevron-left"></i>
				</div>				
				<a href="https://app.workz.com.br/core/backengine/wa0002/wtebx.php?vr=<?php echo $office_id; ?>&qt=<?php echo $dtch; ?>" target="_blank">										
					<div title="Imprimir todos os registros" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
						<i class="far fa-file-pdf"></i>
					</div>				
				</a>					
				
			</div>
			<div style="clear: both"></div>
		</div>
		<div class="large-12 medium-12 small-12">			
			<input class="border-like-input w-rounded-5 cm-pad-5 cm-mg-10-t" onchange="goTo('core/backengine/wa0002/wtebd.php', 'wteba', '<? echo $office_id; ?>|1', this.value)" style="padding: 3px;" type="month" name="" id="" value="<? echo date('Y-m', strtotime($dtch)); ?>"/>
			<div class="clear"></div>
		</div>			
		<div style="overflow: auto;" class="w-rounded-10 background-gray cm-mg-10-t cm-pad-10">
		<?			
		$office = search('cmp', 'companies', '', "id = '{$office_id}'");						
		if(count($office) > 0){				
			$scds = array_column(search('cmp', 'companies_groups', 'emC', "emP = '{$office_id}'"),'emC');
			foreach($scds as $scds_result){					
				$company = search('cmp', 'companies', 'tt', "id = '{$scds_result}'")[0];									
				$monthly_report = 0;
				$wtebr = search('app', 'wa0002_regs_alterado', '', "lgtp = '0' AND dtch = '{$dtch}' AND scid = '{$scds_result}' AND lgst = '0'");
				$wterc = count($wtebr);					
				foreach($wtebr as $wtebt){
					$dds = explode(';', $wtebt['vlch']);
					$pri = $dds[0];
					$jur = $dds[1];
					$jac = $dds[2];
					$vmn = $dds[3];
					$scp = $dds[4];
					$slp = $dds[5];
					$trs = $dds[6];
					?>
					<div class="large-12 medium-12 small-12 position-relative cm-pad-10-h">					
						<div class="cm-pad-10-b cm-pad-5-t w-color-bl-to-or">					
							<a title="Imprimir Relatório" class="w-color-or-to-bl" href="https://app.workz.com.br/core/backengine/wa0002/wtebx.php?vr=<?php echo $office_id; ?>&qt=<?php echo $dtch; ?>&sc=<? echo $wtebt['scid']; ?>" target="_blank"><? echo $company['tt']; ?></a>
						</div>																			
						<div class="large-12 medium-12 small-12 background-white cm-mg-20-b cm-pad-5 w-rounded-5 w-shadow position-relative">								
							<div class="cm-pad-5-t cm-pad-10-h cm-pad-10-b float-left small-6 medium-2P large-2P" title="Principal a pagar">
								<p class="font-weight-500 cm-mg-5-t cm-mg-5-b">Principal (R$)</p>
								<p><? echo $pri; ?></p>									
							</div>
							<div class="cm-pad-5-t cm-pad-10-h cm-pad-10-b float-left small-6 medium-2P large-2P" title="Juros a pagar">			
								<p class="font-weight-500 cm-mg-5-t cm-mg-5-b">Juros (R$)</p>
								<p><? echo $jur; ?></p>									
							</div>
							<div class="cm-pad-5-t cm-pad-10-h cm-pad-10-b float-left small-display-none small-6 medium-2P large-2P" title="Juros acumulados">			
								<p class="font-weight-500 cm-mg-5-t cm-mg-5-b">Juros Ac. (R$)</p>
								<p><? echo $jac; ?></p>									
							</div>
							<div class="cm-pad-5-t cm-pad-10-h cm-pad-10-b float-left small-display-none small-6 medium-2P large-2P" title="Variação monetária">
								<p class="font-weight-500 cm-mg-5-t cm-mg-5-b">Var. Mon. (R$)</p>
								<p><? echo $vmn; ?></p>
							</div>
							<div class="cm-pad-5-t cm-pad-10-h cm-pad-10-b float-left small-display-none small-6 medium-2P large-2P" title="Transferência entre longo e curto prazos">
								<p class="font-weight-500 cm-mg-5-t cm-mg-5-b">Transfer. (R$)</p>
								<p><? echo $trs; ?></p>
							</div>							
							<div class="clear"></div>													
							<div onclick="goTo('core/backengine/wa0002/wtebd.php', 'wteba', '<? echo $office_id; ?>|1&del=<? echo $wtebt['id']; ?>', '');" title="Excluir registro" style="height: 20px; width: 20px; padding-top: 2px;" class="position-absolute abs-r-5 abs-t-5 w-nav pointer w-bkg-dark-to-red-transparent white text-center w-rounded-5 fs-b">
								<i class="fas fa-trash"></i>
							</div>
						</div>
					</div>						
					<?
					$monthly_report++;
				}										
			}
			if($monthly_report == 0){
				?>				
				<div class=""><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>Não foram encontrados registros de apuração para <? echo strftime('%B de %Y', strtotime($dtch)); ?>.</div>
				<?
			}				
		}else{
			?>
			<div class=""><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>Esta conta ainda não possui clintes.</div>			
			<?
		}			
		?>
		</div>
	</div>	
</div>
<?
unset($office_id);
?>