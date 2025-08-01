<?php
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

date_default_timezone_set('America/Sao_Paulo');
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/randomPassword.php');
require_once('../../common/getUserAccessibleEntities.php');

// Função para criptografar a senha
function criptografar($senha, $encryptionKey){
	$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
	$senhaCriptografada = openssl_encrypt($senha, 'aes-256-cbc', $encryptionKey, 0, $iv);
	return base64_encode($iv . $senhaCriptografada);
}

// Função para descriptografar a senha
function descriptografar($senhaCriptografada, $encryptionKey){
	$data = base64_decode($senhaCriptografada);
	$iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
	$senhaCriptografada = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
	return openssl_decrypt($senhaCriptografada, 'aes-256-cbc', $encryptionKey, 0, $iv);
}

//Adicionar registro
if(isset($_SESSION) && isset($_GET) && !empty($_GET)){
	$wa0004_keys = search('app', 'wa0004_keys', 'id,ky', "us = '{$_SESSION['wz']}'")[0];
	$encryptionKey = $wa0004_keys['ky'];
	$id_psw = $wa0004_keys['id'];
	if(!empty($encryptionKey)){
						
		//Recuperar registros
		$data = array();
		
		//TEAMS PASSWORDS
		$userEntities = getUserAccessibleEntities($_SESSION['wz']);
		$teams = $userEntities['teams'];
		foreach($teams as $teamId){				
			if($teamReg = search('app', 'wa0004_keys', 'id,ky', "cm = '{$teamId}'")){															
				$team = hash('sha256', $teamId . $teamReg[0]['id']);
				$regs = search('app', 'wa0004_regs', '', "sh = '".$team."'");
				$encrypTeamKey = $teamReg[0]['ky'];
				foreach($regs as $reg){
					$data[] = array(
						'id' => $reg['id'],
						'descricao' => descriptografar($reg['ds'], $encrypTeamKey),
						'site' => descriptografar($reg['ur'], $encrypTeamKey),
						'usuario' => descriptografar($reg['lg'], $encrypTeamKey),
						'senha' => descriptografar($reg['pw'], $encrypTeamKey),
						'team' => $teamId
					);						
				}
			}							
		}
		
		//USER PASSWORDS
		$user = hash('sha256', $_SESSION['wz'] . $id_psw);
		$regs = search('app', 'wa0004_regs', '', "us = '".$user."'");
		foreach($regs as $reg){
			if(empty($reg['sh'])){
				$data[] = array(
					'id' => $reg['id'],
					'descricao' => descriptografar($reg['ds'], $encryptionKey),
					'site' => descriptografar($reg['ur'], $encryptionKey),
					'usuario' => descriptografar($reg['lg'], $encryptionKey),
					'senha' => descriptografar($reg['pw'], $encryptionKey),
					'team' => descriptografar($reg['sh'], $encryptionKey)
				);
			}
		}
		
		foreach($data as $pass){
		?>
		<div class="tab position-relative large-3 medium-6 small-12 float-left cm-pad-10">
			<div id="<?= $pass['id'] ?>" class="cm-mg-15-t w-rounded-20 background-white w-shadow-1 fs-e position-relative">
				<div id="<?= $pass['id'] ?>_main" class="fs-c orange">
					<div style="height: 40px; width: 40px; top: -15px" class="text-center position-absolute abs-l-25 display-center-general-container fs-f display-block white"><div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white w-shadow-2 z-index-0" style="transform: rotate(20deg); background: <?=  $colors[0] ?>;"></div><i class="fas fa-key pointer z-index-1 centered"></i></div>
					<input id="<?= $pass['id'] ?>_descricao" class="cm-mg-30-t font-weight-500 border-none large-12 medium-12 small-12 cm-pad-15 orange" value="<?= $pass['descricao'] ?>" />					
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
						<label for="<?= $pass['id'] ?>_site" class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-15-l"><i class="fas fa-globe-americas"></i></label>
						<input onclick="copiarParaClipboard(this)" id="<?= $pass['id'] ?>_site" class="float-left border-none large-10 medium-10 small-10 cm-pad-15-r" style="height: 45px" value="<?= $pass['site'] ?>" ></input>
					</div>
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
						<label for="<?= $pass['id'] ?>_usuario" class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-15-l"><i class="fas fa-user"></i></label>
						<input onclick="copiarParaClipboard(this)" id="<?= $pass['id'] ?>_usuario" class="float-left border-none large-10 medium-10 small-10 cm-pad-15-r" style="height: 45px" value="<?= $pass['usuario'] ?>" ></input>
					</div>
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
						<label for="<?= $pass['id'] ?>_senha" class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-15-l"><i class="fas fa-ellipsis-h"></i></label>
						<input onclick="copiarParaClipboard(this)" style="height: 45px; width: calc(100% - 60px)" type="password" id="<?= $pass['id'] ?>_senha" class="float-left border-none large-10 medium-10 small-10" value="<?= $pass['senha'] ?>"></input>
						<a style="height: 25px; width: 25px;" class="fas fa-eye cm-pad-5 w-all-bl-to-or pointer w-circle float-right cm-mg-15-r" onclick="showPassword(this)"></a>
					</div>
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
						<label for="<?= $pass['id'] ?>_team" class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-15-l"><i class="fas fa-users"></i></label>
						<select id="<?= $pass['id'] ?>_team" class="float-left border-none large-10 medium-10 small-10" style="height: 45px">
							<option value="0">Somente eu</option>
							<?php
							foreach($teams as $team){
								$teamInfo = search(cmp, teams, 'tt', "id = ".$team."")[0];
								?>
								<option value="<?= $team ?>" <?= ($pass['team'] == $team) ? 'selected' : '' ?>><?= $teamInfo['tt'] ?></option>
							<?php
							}
							?>
						</select>
					</div>
					<div class="fs-c">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input" style="height: 45px">
							<div class="float-left large-4 medium-4 small-4 text-ellipsis cm-pad-15-l">Copiar ao clicar</div>
							<div class="onoffswitch align-right cm-mg-15-r">
								<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="switch-<?= $pass['id'] ?>" tabindex="0" checked>
								<label class="onoffswitch-label" for="switch-<?= $pass['id'] ?>"></label>
							</div>
						</div>				
					</div>
				</div>
				<div class="cm-pad-15 large-12 medium-12 small-12 clearfix">
					<div class="float-right" style="width: calc(100% - 60px)">
						<div onclick="confirmarSalvamento(this)" style="color: <?= $colors[0] ?>;" class="w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 pointer fs-c">
							<div style="height: 30px; width: 30px; color: <?= $colors[0] ?>;" class="text-center display-center-general-container fs-b float-left display-block white">
							<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
							<i class="fas fa-pen pointer z-index-1 fs-c centered"></i>
							</div>
							<a class="font-weight-500 cm-pad-10-l display-block ">Salvar</a>
						</div>
					</div>
					<div class="float-left" style="width: 60px">
						<div onclick="confirmarExclusao(this)" style="height: 40px; width: 40px; color: <?= $colors[0] ?>;" class="cm-mg-5-t cm-mg-5-l display-center-general-container float-left display-block w-color-or-to-wh pointer" title="Excluir registro">
							<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 w-all-or-to-bl z-index-0" style="transform: rotate(20deg);"></div>
							<i class="fas fa-trash z-index-1 fs-c white centered"></i>
						</div>						
					</div>
				</div>
			</div>
		</div>
		<?php
		}			
	}
}
?>