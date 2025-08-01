<!-- EMPRÉSTIMOS BNDES -->
<?
$wurl = explode('/', $_SERVER['DOCUMENT_ROOT']);
$eurl = end($wurl);
?>
<div class="card">	
	<?
	if($eurl != 'sistemas'){
	?>
	<h4 class="fs-c uppercase border-b-brown-3 cm-pad-20-b cm-pad-5-t"><div class="w-icon-or"><i class="fas fa-th-large"></i></div> Menu BNDES</a></h4>
	<?
	}
	?>
	
	<div class="wgrid cm-mg-10-t w-community-container">
		
		<?			
		if($eurl != 'sistemas'){
		?>
		<div onclick="goTo('wtebd.php', 'wteba', '1', '')" class="btn-wgrid large-4 medium-2 small-4 float-left cm-pad-10 uppercase fs-b font-weight-600">
			<div class="large-12 w-square position-relative pointer">
				<div class="w-rounded-5 w-square-content position-relative vertical-align w-bkg-tr-gray border-like-input">
					<div class="header-btn-wgrid display-none">
						<div class="wgrid-block">
							<div class="wgrid-centered">
								<a><i class="mdi mdi-file-document-box"></i></a>
							</div>
						</div>
					</div>
					<div class="footer-btn-wgrid text-center">
						<a>Resumo</a>
					</div>
				</div>
			</div>
		</div>
		
		<div onclick="goTo('wccca.php', 'wteba', '1', '1');" class="btn-wgrid large-4 medium-2 small-4 float-left cm-pad-10 uppercase fs-b font-weight-600">
			<div class="large-12 w-square position-relative pointer">
				<div class="w-rounded-5 w-square-content position-relative vertical-align w-bkg-tr-gray border-like-input">
					<div class="header-btn-wgrid display-none">
						<div class="wgrid-block">
							<div class="wgrid-centered">
								<a><i class="mdi mdi-store"></i></a>
							</div>
						</div>
					</div>
					<div class="footer-btn-wgrid">
						<a>Sociedades</a>
					</div>
				</div>
			</div>
		</div>
		<?
		}
		?>			
		
		<div onclick="wteba(1,'', '')" class="btn-wgrid large-4 medium-2 small-4 float-left cm-pad-10 uppercase fs-b font-weight-600">
			<div class="large-12 w-square position-relative pointer">
				<div class="w-rounded-5 w-square-content position-relative vertical-align w-bkg-tr-gray border-like-input">
					<div class="header-btn-wgrid display-none">
						<div class="wgrid-block">
							<div class="wgrid-centered">
								<a><i class="mdi mdi-file-document-box"></i></a>
							</div>
						</div>
					</div>
					<div class="footer-btn-wgrid text-center">
						<a>Liberações</a>
					</div>
				</div>
			</div>
		</div>
		
		<div onclick="wtebb(1,'', '', '', '')" class="btn-wgrid large-4 medium-2 small-4 float-left cm-pad-10 uppercase fs-b font-weight-600">
			<div class="large-12 w-square position-relative pointer">
				<div class="w-rounded-5 w-square-content position-relative vertical-align w-bkg-tr-gray border-like-input">
					<div class="header-btn-wgrid display-none">
						<div class="wgrid-block">
							<div class="wgrid-centered">
								<a><i class="mdi mdi-calculator"></i></a>
							</div>
						</div>
					</div>
					<div class="footer-btn-wgrid text-center">
						<a>Mov. Financ.</a>
					</div>
				</div>
			</div>
		</div>
		
		<div onclick="goTo('wtebc.php', 'wteba', '1', '');" class="btn-wgrid large-4 medium-2 small-4 float-left cm-pad-10 uppercase fs-b font-weight-600">
			<div class="large-12 w-square position-relative pointer">
				<div class="w-rounded-5 w-square-content position-relative vertical-align w-bkg-tr-gray border-like-input">
					<div class="header-btn-wgrid display-none">
						<div class="wgrid-block">
							<div class="wgrid-centered">
								<a><i class="mdi mdi-format-list-bulleted"></i></a>
							</div>
						</div>
					</div>
					<div class="footer-btn-wgrid text-center">
						<a>Registros</a>
					</div>
				</div>
			</div>
		</div>
		
		<div class="clear"></div>
	</div>
	
</div>
<?			
if($eurl != 'sistemas'){
	include('config/wteb.php');
	?>
	<input class="modal-state" id="wtebm" type="checkbox"/>
	
	<div class="modal">
		<label class="modal__bg" for="wtebm"></label>
			<div id="wtebm-target" class="w-rounded-10 modal__inner cm-pad-30 large-4">						
			<label class="modal__close w-color-bl-to-or fa-1_5x pointer position-absolute" style="z-index: 9999999;" for="wtebm"><i class="fas fa-times-circle fa-1x"></i></label>
			<!-- MODAL CONTENT -->
			<div class="height-100 position-relative">
				
				<div class="position-absolute large-12 medium-12 small-12" style="height: calc(100% - 73px); z-index: 0;">
					<h2 class="title-general border-b-brown-3 cm-pad-10-b">Nova Liberação</h2>
					<div class="large-12 overflow-y-auto" style="height: calc(100% - 60px)">
						<iframe class="w-shadow border-none w-rounded-5 large-12 medium-12 small-12 cm-mg-20-b" style="height: 35px; display: none;" id="wteb1" name="wteb1" class="large-12 medium-12 small-12">
						</iframe>
				<form method="POST" action="async/engine/wteb1.php" target="wteb1">
						<input type="hidden" name="url" value="<? echo $slashes[4]; ?>"></input>
						<div class="w-form">
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Empresa</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<select name="sc" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10">
										<?
										foreach($SLib as $unds){											
										?>
										<option value="<? echo  $unds['id']; ?>"><? $ps_data = 'firstname'; $ps_id = $unds['ps']; include($_SERVER['DOCUMENT_ROOT'].'/protspot/protspot_getid.php');?></option>
										<?
										}
										?>
									</select>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Contrato</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="ct" style="width: 70%" type="text" class="float-left input-border border-like-input cm-pad-10 w-rounded-5" onkeypress="formatar('##.###.###', this)" placeholder="Contrato" maxlength="10"></input>
									<input name="nl" style="width: calc(30% - 10px)" class="cm-mg-10-l float-left input-border border-like-input cm-pad-10 w-rounded-5" type="text" placeholder="Liberação" value="" maxlength="3"></input>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Subcrédito</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="sb" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" placeholder="Ex.: A1"></input>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Moeda Contratual</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<select name="um" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10">
										<option value="1">URTJLP (360 dias)</option>
										<option value="3">URTJLP (365 dias)</option>
										<option value="2">UMIPCA (252 dias)</option>
									</select>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Valor Liberado</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="lb" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" placeholder="Ex.: 1000000,00"></input>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Prestação</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="pr" style="width: calc(50% - 5px)" type="text" name="office_pass" id="office_pass" class="float-left input-border border-like-input cm-pad-10 w-rounded-5" placeholder="Nº de Prestações"></input>
									<input name="tx" style="width: calc(50% - 5px)" class="cm-mg-10-l float-left input-border border-like-input cm-pad-10 w-rounded-5" type="text" name="office_id" id="office_id" placeholder="Spread Fixo" value=""></input>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Início</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="l0" type="date" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10"></input>
								</div>
								<div class="clear"></div>
							</div>
							<div class="large-12 medium-12 small-12 cm-mg-20-b">
								<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
									<label>Término</label>
								</div>
								<div class="large-9 medium-9 small-12 float-right">
									<input name="j0" type="date" class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10"></input>
								</div>
								<div class="clear"></div>
							</div>
						</div>
					</div>
				</div>
				<div class="position-absolute abs-b-0 large-12 medium-12 small-12">
					<hr>
					<button type="submit" onclick="document.getElementById('wteb1').style.display = 'block';" class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-right pointer w-shadow" title="Incluir Liberação">Incluir</button>
				</div>
				</form>
			</div>			
			<!-- /MODAL CONTENT -->
		</div>
	</div>
	<?
}
?>