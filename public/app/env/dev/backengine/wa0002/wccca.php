<?
session_start();

$wurl = explode('/', $_SERVER['DOCUMENT_ROOT']);
$eurl = end($wurl);

require_once($_SERVER['DOCUMENT_ROOT']."/config/db.php");

	if(!isset($_GET['pg'])){
		$_GET['pg'] = 1;
	}

	$tp = $_GET['vr'];
	$of = $_SESSION['office_id'];
	$pn = $_GET['pg'];
	
	$kb = ($pn * 8) - 1;
	$ka = ($kb - 7);

	$WR1A = $pdo->prepare("SELECT * FROM WR01 WHERE of = '".$of."' AND tp = '".$tp."';");
	$WR1A->execute();
	$WR1A_count = $WR1A->rowCount(PDO::FETCH_ASSOC);
	
	$np = ceil($WR1A_count / 8); //Número de paginas
	
?>
<div class="card <?if($eurl != 'sistemas'){?>position-relative<?}?>">
	<div class="card-body">
		<?if($eurl != 'sistemas'){?>
		<div class="w-page-header">
			<h4 class="fs-c uppercase border-b-brown-3 cm-pad-20-b cm-pad-5-t"><div class="w-icon-or fs-c"><i class="fas fa-city"></i></div> Sociedades</a></h4>
			<div class="position-absolute abs-r-0 abs-t-0 w-nav">				
				<label for="wcccm">
					<div title="Incluir cadastro" class="w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu" data-toggle="modal" data-target="#Modal">
						<i class="mdi mdi-plus"></i>
					</div>
				</label>				
			</div>
			<div style="clear: both"></div>
		</div>
		<?}else{?>
		<div class="w-page-header">
			<div class="w-nav">
				<div onclick="goTo('wcccz.php', 'wccc', '', '')" title="Voltar" class="w-nav-button w-exit">
					<i class="mdi mdi-keyboard-backspace"></i>
				</div>
				<div title="Incluir cadastro" class="w-nav-button" data-toggle="modal" data-target="#Modal">
					<i class="mdi mdi-plus"></i>
				</div>
			</div>
		</div>			
		<?}
		if($WR1A_count == 0){
			?>
			<div class="modal-aviso cm-mg-20-t cm-pad-10 w-rounded-5 uppercase font-weight-600 fs-b background-gray"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>A conta não possui cadastros deste tipo.</div>			
			<?			
		}else{
		?>
		<div class="table-responsive background-gray w-rounded-10 cm-mg-20-t cm-pad-20">
			<table class="table large-12 medium-12 small-12">
				<thead>
				<tr>
					<th class="cm-pad-5-b" style="width: 25%;">Nome</th>
					<th class="cm-pad-5-b" style="width: 25%;">Documento</th>				
					<th class="cm-pad-5-b" style="width: 25%;">Cidade</th>			
					<th class="cm-pad-5-b" style="width: 25%;"></th>
				</tr>
				</thead>
				<tbody class="text-center">
				<?
				$a_ps = array();
				$a_id = array();				
				while($vclb = $WR1A->fetch(PDO::FETCH_ASSOC)){
					$a_ps[] = $vclb['ps'];
					$a_id[] = $vclb['id'];
				}				
				for($i=$ka;$i<=$WR1A_count && $i >= 0;$i++){
				if (array_key_exists($i, $a_ps)){
				?>
				<tr>	
					<td style="width: 25%;" class="cm-pad-5 fs-c"><? if($tp == 1){$ps_data = 'firstname';}else{$ps_data = 'fullname';} $ps_id = $a_ps[$i]; include($_SERVER['DOCUMENT_ROOT'].'/protspot/protspot_getid.php'); ?></td>
					<td style="width: 25%;" class="cm-pad-5 fs-c"><? $ps_data = 'document'; $ps_id = $a_ps[$i]; include($_SERVER['DOCUMENT_ROOT'].'/protspot/protspot_getid.php'); ?></td>				
					<td style="width: 25%;" class="cm-pad-5 fs-c"><? $ps_data = 'city'; $ps_id = $a_ps[$i]; include($_SERVER['DOCUMENT_ROOT'].'/protspot/protspot_getid.php'); ?></td>				
					<td style="width: 25%;" class="action position-relative">
						<?if($tp == 3){?><label title="Posição Societária" onclick="goTo('subfnc/wccca_11.php', 'wccc', '<? echo $pn; ?>', '<? echo $a_ps[$i]; ?>')" class="w-pointer float-right"><i class="mdi mdi-tie"></i></label><?}?>					
						<!--
						<label title="Excluir"  class="w-pointer float-right w-rounded-5 background-white border-like-input border-none pointer font-weight-600 ubuntu fs-c"><i class="mdi mdi-delete-forever"></i></label>					
						-->
						<?if($eurl != 'sistemas'){?>
						<div title="Desprezar registro" onclick="wchange('wccfg', '<? echo $a_ps[$i]; ?>', '<? echo $pn; ?>', '<? echo $tp; ?>')" style="height: 20px; width: 20px; padding-top: 2px;" class="position-absolute abs-r-5 abs-t-5 w-nav pointer w-bkg-dark-to-red-transparent white text-center w-rounded-5 fs-b" onclick="wchange('wtefb', '1|<? echo $wtebt['id']; ?>', '', '<? echo date('Y-m-01', strtotime($dtch)); ?>')">
							<i class="fas fa-times"></i>
						</div>
						<?}?>						
					</td>
				</tr>
				<?
				}
			}
			?>
				</tbody>
			</table>
		</div>
		<?
		}
		?>
	</div>
	<!--
	<div class="w-btn-group" role="group" aria-label="Basic example">
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
		if($z > 0){
		?>
		<button style="width: 25px; height: 25px;" class="w-circle w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" <?if($pn == 1){ echo 'disabled'; }?> class="" onclick="goTo('wccca.php', '<?if($eurl == 'sistemas'){?>wccc<?}else{?>wteba<?}?>', '1', '<? echo $tp; ?>');"><i class="mdi mdi-arrow-left"></i></button>
		<?
		for($a=$w;$a<=$z && $a > 0;$a++){
		if($pn == $a){
		?>		
		<button style="width: 25px; height: 25px;" class="w-circle w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" disabled><a class="gray"><? echo $a; ?></a></button>
		<?
		}else{
		?>
		<button style="width: 25px; height: 25px;" class="w-circle w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('wccca.php', '<?if($eurl == 'sistemas'){?>wccc<?}else{?>wteba<?}?>', '<? echo $a; ?>', '<? echo $tp; ?>');" ><? echo $a; ?></button>
		<?
		}
		}
		?>
		<button style="width: 25px; height: 25px;" class="w-circle w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" <? if($pn == $np){ echo 'disabled'; }?> onclick="goTo('wccca.php', '<?if($eurl == 'sistemas'){?>wccc<?}else{?>wteba<?}?>', '<? echo $np; ?>', '<? echo $tp; ?>');"><i class="mdi mdi-arrow-right"></i></button>
		<?
		}
		?>
	</div>
	-->
</div>
<?			
if($eurl != 'sistemas'){	
?>
<input class="modal-state" id="wcccm" type="checkbox"/>	
<div class="modal">
	<label class="modal__bg" for="wcccm"></label>
		<div id="wcccm-target" class="w-rounded-10 modal__inner cm-pad-30 large-4">						
		<label onclick="goTo('wccca.php', 'wteba', '<? echo $pn; ?>', '<? echo $tp; ?>')" class="modal__close w-color-bl-to-or fa-1_5x pointer position-absolute" style="z-index: 9999999;" for="wcccm"><i class="fas fa-times-circle fa-1x"></i></label>
		<!-- MODAL CONTENT -->
		<div class="height-100 position-relative">				
			<div class="position-absolute large-12 medium-12 small-12" style="height: calc(100% - 73px); z-index: 0;">
				<h2 class="title-general border-b-brown-3 cm-pad-20-b">Novo Cadastro</h2>
				<div class="large-12 overflow-y-auto" style="height: calc(100% - 60px)">								
					<div class="w-form">
						<div id="wccca_add" class="overflow-x-hidden">															
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<?if($tp == 1 || $tp == 2){?><label>CNPJ</label><?}else{?><label>CPF</label><?}?>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" type="text" maxlength="18" id="cpf" name="cpf" value="" <?if($tp == 1 || $tp == 2){?> onkeypress="formatar('##.###.###/####-##', this)"<?}else{?>onkeypress="formatar('###.###.###-##', this)"<?}?>></input>										
								</div>
								<div class="clear"></div>
							</div>													
						</div>
					</div>
				</div>
			</div>
			<div class="position-absolute abs-b-0 large-12 medium-12 small-12">
				<hr>
				<button type="submit" onclick="wccca_add(<? echo $_SESSION['id']; ?>, <? echo $app_id; ?>, <? echo $app_secret; ?>, document.getElementById('cpf').value, <? echo $tp; ?>)"  class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-right pointer w-shadow" title="Incluir Liberação">Continuar</button>
			</div>
			
		</div>			
		<!-- /MODAL CONTENT -->
	</div>
</div>
<?
}else{
?>
<!-- Modal starts -->
<div class="modal fade" id="Modal" tabindex="-1" role="dialog" aria-labelledby="ModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="ModalLabel">Novo cadastro</h5>
				<i onclick="goTo('wccca.php', 'wccc', '<? echo $pn; ?>', '<? echo $tp; ?>')" class="settings-close mdi mdi-close" data-dismiss="modal" aria-label="Close"></i>
			</div>			
			<div id="wccca_add" class="modal-body w-modal-content">
				<div class="form-group" style="margin-bottom: 20px;">					
					<?
					if($tp == 1 || $tp == 2){
					?>
					<label>Informe o nº de CNPJ:</label>
					<input type="text" maxlength="18" class="form-control" id="cpf" name="cpf" value="" onkeypress="formatar('##.###.###/####-##', this)"></input>
					<?
					}else{
					?>
					<label>Informe o nº de CPF:</label>
					<input type="text" maxlength="14" class="form-control" id="cpf" name="cpf" value="" onkeypress="formatar('###.###.###-##', this)"></input>
					<?
					}
					?>
				</div>					
				<button onclick="wccca_add(<? echo $_SESSION['id']; ?>, <? echo $app_id; ?>, <? echo $app_secret; ?>, document.getElementById('cpf').value, <? echo $tp; ?>, '')" title="Pesquisar" type="button" class="btn btn-gradient-danger w-btn btn-icon-text">
					<i class="mdi mdi-magnify"></i>
					Pesquisar
				</button>
			</div>				
		</div>
	</div>
</div>
<!-- Modal Ends -->
<?
}
?>