<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');

session_start();
include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
date_default_timezone_set('America/Sao_Paulo');
?>

	<div id="new" class="large-10 medium-12 small-12 position-relative centered cm-mg-20-t">					
		<h3 class="text-ellipsis cm-mg-20-t">Nova <? if($_GET['qt'] == 1){?>Tarefa<?}elseif($_GET['qt'] == 2){?>Pasta<?}?></h3>
		<div class="position-absolute abs-t-0 abs-r-0">
			<span onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', '');" class="fa-stack w-color-bl-to-or pointer float-right " style="vertical-align: middle;">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
			</span>	
			<span onclick="formValidator2('divForm', 'core/backengine/wa0001/<? if($_GET['qt'] == 1){?>m_task.php<?}elseif($_GET['qt'] == 2){?>folder.php<?} ?>', 'new');" class="fa-stack w-color-or-to-bl pointer float-right cm-mg-20-b" style="vertical-align: middle;" title="Prosseguir">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-save fa-stack-1x fa-inverse"></i>					
			</span>	
		</div>
		<div class="w-form" class="large-12 medium-12 small-12 cm-pad-25-t">
			<div class="large-12 medium-12 small-12 cm-mg-25-t border-t-input cm-pad-25-t">
				<div class="large-12 medium-12 small-12 overflow-y-auto">
					<div id="divForm" class="w-form">				
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Título</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">
								<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" id="tt" <? if($_GET['qt'] == 1){?>name="tsktt"<?}elseif($_GET['qt'] == 2){?>name="tgttt"<?}?> type="text" placeholder="Título" required></input>									
							</div>
							<div class="clear"></div>
						</div>							
						<?
						if($_GET['qt'] == 1){
						?>
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Pasta</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">
								<?
								$folders = search('app', 'wa0001_tgo', '', "us = '".$_SESSION['wz']."' AND st = '0'");
								?>
								<select name="tg" id="tg" class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" required>							
									<option value="" selected disabled>Selecione</option>
									<?
									foreach($folders as $folder){
									?>
									<option value="<? echo $folder['id']; ?>"><? echo $folder['tt']; ?></option>
									<?								
									}	
									?>							
								</select>						
							</div>
							<div class="clear"></div>
						</div>						
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Compartilhamento</label>
							</div>
							<?
							include('user_cmp.php');
							?>
							<div class="large-9 medium-9 small-12 float-right">
								<select name="cm" id="cm" class="large-12 medium-12 small-12 w-rounded-10 input-border border-like-input cm-pad-10">
									<option value="">Não compartilhar</option>
									<?
									foreach($teams as $team){
										$teamInfo = search('cmp', 'teams', 'tt', "id = '".$team."'")[0];
									?>
									<option value="<? echo $team; ?>">Compartilhar com <? echo $teamInfo['tt']; ?></option>
									<?	
									}
									?>
								</select>
							</div>							
							<div class="clear"></div>							
						</div>
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Frequência</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">
								<select name="pr" id="pr" class="large-12 medium-12 small-12 w-rounded-10 input-border border-like-input cm-pad-10">
									<option value="0">Única</option>
									<option value="1">Diária</option>
									<option value="2">Semanal</option>
									<option value="3">Mensal</option>
									<option value="4">Bimestral</option>
									<option value="5">Trimestral</option>
									<option value="6">Semestral</option>
									<option value="7">Anual</option>
								</select>
							</div>
							<div class="clear"></div>
						</div>
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Prazo</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">
								<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" id="df" name="df" type="datetime-local" placeholder="" required></input>									
							</div>
							<div class="clear"></div>
						</div>						
						<div class="large-12 medium-12 small-12 cm-mg-20-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Descrição</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">									
								<textarea id="ds" name="ds" class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" placeholder="Descrição..." value="" ></textarea>
							</div>
							<div class="clear"></div>
						</div>										
						<div class="large-12 medium-12 small-12">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
								<label class="font-weight-500">Etapas</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right border-like-input cm-pad-20 w-rounded-15 background-white-transparent-50">
								<div id="inputContainer">							
									<div id="taskStep_0" class="fieldsContainer">
										<input class="large-8 medium-8 small-8 w-rounded-10 input-border border-like-input cm-pad-10 cm-mg-10-b required float-left" id="tsksp" name="tsksp_0" type="text" value=""></input>										
										<div class="large-4 medium-4 small-4 cm-pad-10-l float-right">
											<input onchange="" type="datetime-local" max="" name="tsksp_dt_0" class="large-12 medium-12 small-12 w-rounded-10 input-border border-like-input cm-pad-10" value=""></input>
										</div>
										<div class="clear"></div>
									</div>																	
								</div>																															
								<div id="addButtonContainer" class="large-12 medium-12 small-12 text-center cm-mg-10-t">
									<a class="pointer uppercase font-weight-600 w-color-bl-to-or" onclick="addTaskStep();">Adicionar Etapa</a>
									|
									<a class="pointer uppercase font-weight-600 w-color-bl-to-or" onclick="remTaskStep();">Remover Etapa</a>
								</div>						
							</div>									
							<div class="clear"></div>
						</div>
						<?
						}					
						?>									
					</div>
				</div>
			</div>
		</div>
	</div>		

