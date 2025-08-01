<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');
session_start();
include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
date_default_timezone_set('America/Sao_Paulo');

$tipos = [
    1 => "o Documento",
    5 => "a Página da Web"    
];

$tipo = $_GET['qt'] ?? null; // Evita erro se 'qt' não for passado
$id = $_GET['vr'] ?? null;
$modoEdicao = !empty($id);
?>
<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
	<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
		<div onclick="<?= $modoEdicao ? 'toggleSidebar()' : 'loadMainMenu()' ?>" class="display-center-general-container w-color-bl-to-or pointer">
			<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
			<a>Voltar</a>
		</div>
	</div>		
</div>
<?php
// Se for edição, busca os dados da tarefa/pasta/rotina
$dados = $modoEdicao ? (search('hnw', 'hpl', 'id,us,tp,ca,dt,ci,cm,em,pc,im,tt,kw,st', "id = '$id'")[0] ?? []) : [];

//Empresas e equipes do usuário
include('../../common/getUserAccessibleEntities.php');
$userData = getUserAccessibleEntities($_SESSION['wz']);
$companies = $userData['companies'];
$teams = $userData['teams'];

//Se qt = 1 - Documento
//Se qt = 5 - Página da web

if(isset($_GET['qt'])){
	
	//Se houver post = Edição
	//Se não houver post = Nova página

	$postExists = 0;
	$id = 0;
	$st = 0;
	$lg = 0;
	$im = '';
	if (!empty($_GET['vr'])) {
		$myPost = search('hnw', 'hpl', '', "id = '" . $_GET['vr'] . "'")[0];
		$id = $myPost['id'];
		$tt = $myPost['tt'];
		$st = $myPost['st'];
		$dt = $myPost['dt'];
		$im = $myPost['im'];
		$kw = $myPost['kw'];
		$em = $myPost['em'];
		$cm = $myPost['cm'];
		$ca = $myPost['ca'];
		$tp = $myPost['tp'];
		$lk = $myPost['lk'];
		$lg = $myPost['lg'];

		if (substr($im, 0, 15) == '/uploads/posts/') {
			$im = 'https://workz.com.br' . $im;
		} else {
			$im = base64_decode($im);
		}
		$postExists = 1;
	}
	if(!empty($_GET['qt'])){
		$tp = $_GET['qt'];
	}
	?>
	<div class="large-12 medium-12 small-12 text-center gray">
		<h2><?= $modoEdicao ? "Editar " : "Nov"?><?= $tipos[$tipo] ?></h2>
	</div>
	<div id="divForm" class="large-12 medium-12 small-12 cm-pad-20 centered">  
		<div class="w-shadow w-rounded-15">
		
			<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white w-rounded-15-t">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Título</div>
				<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required"
					style="height: 45px" id="tt" name="tt" placeholder="Adicionar o título d<?= $tipos[$tipo] ?>" value="<?= $modoEdicao ? htmlspecialchars($dados['tt'] ?? '') : '' ?>">
			</div>		
			
			<?php
			if ($tipo == 1){
			//CAMPOS EXCLUSIVOS PARA DOCUMENTOS	
			?>
			<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Imagem da capa</div>
								
				<div class="float-left border-none large-10 medium-10 small-8" style="height: 135px">
					<div id="bkgForm" class="large-12 small-12 medium-12 height-100">
						<input accept="image/*" type='file' name="im" id="imgInp" class="display-none" onchange="imgPreview(this)"/>
						<label for="imgInp" class="large-12 small-12 medium-12 w-bkg-wh-to-gr display-center-general-container height-100 pointer" title="Carregar imagem">
							<span class="<?= !empty($im) ? 'display-none' : '' ?> fs-g fa-stack pointer centered">
								<i class="fas fa-upload fa-stack-1x"></i>
							</span>
							<span class="<?= empty($im) ? 'display-none' : '' ?> large-12 medium-12 small-12 height-100 centered">
								<img class="large-12 medium-12 small-12 height-100" src="<?= ($postExists == 1) ? $im : '#' ?>" style="object-fit: cover; object-position: center;" />
							</span>								
						</label>						
					</div>
				</div>					
			</div>
			
			<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Categoria</div>			
				<select name="ca" id="ca" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">												
					<option selected disabled value="">Selecione</option>
					<?php
					$categories = search('hnw', 'hca', '', "se >= 0 ORDER BY ca ASC");
					foreach ($categories as $category) {
						?>
						<option value="<?= $category['id']; ?>"
							<?php if ($postExists == 1 && $ca == $category['id']) { echo 'selected'; } ?>>
							<?= $category['ca']; ?>
						</option>
						<?php
					}
					?>
				</select>
			</div>	
			
			<?php } 
			//CAMPOS EXCLUSIVOS PARA PÁGINAS DA WEB
			if ($tipo == 5){
			?>
			<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Definir como</div>
				<?php
				$folders = search('app', 'wa0001_tgo', '', "us = '{$_SESSION['wz']}' AND st = '0'");
				?>
				<select name="lg" id="lg" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">												
					<option value="0" <?php if ($lg == 0) { echo 'selected'; } ?>>Página da Web</option>
					<option value="1" <?php if ($lg == 1) { echo 'selected'; } ?>>Seção do Perfil</option>
				</select>
			</div>
			
			<?php
			}
			?>
			
			<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Publicar em</div>				
				<select name="cm" id="cm" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">																	
					<option value="" selected disabled>Selecione</option>
					<option value="0" <?= ($postExists == 1 && $em == 0 && $cm == 0) ? 'selected' : '' ?>>Meu Perfil</option>
					<?php 
					if (count($companies) > 0) { 
					?>
					<optgroup label="Negócio">
						<?php
						foreach ($companies as $user_company) {
							$company = search('cmp', 'companies', 'id,tt,pg', "id = '" . $user_company . "'");
							if (count($company) > 0) {
							?>
							<option value="em,<?= $company[0]['id']; ?>" <?= ($postExists == 1 && $em == $company[0]['id']) ? 'selected' : '' ?>>								
								<?= $company[0]['tt']; ?><?= ($company[0]['pg'] == 0) ? ' (Desativada)' : '' ?>								
							</option>
							<?php
							}
						}
						?>
					</optgroup>
					<?php 
					} 
					if (count($teams) > 0) { ?>
					<optgroup label="Equipe">
						<?php
						foreach ($teams as $user_team) {
							$team = search('cmp', 'teams', 'id,tt,pg', "id = '" . $user_team . "'");
							if (count($team) > 0) {
							?>
							<option value="cm,<?= $team[0]['id']; ?>"<?= ($postExists == 1 && $cm == $team[0]['id']) ? 'selected' : '' ?>>								
								<?= $team[0]['tt']; ?><?= ($team[0]['pg'] == 0) ? ' (Desativada)' : '' ?>								
							</option>
							<?php
							}
						}
						?>
					</optgroup>
					<?php } ?>
				</select>
			</div>
			
			<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input w-rounded-15-b">
				<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
					<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Palavras-chave</div>																												
					<textarea onchange="" class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 135px; line-height: 1.5em;" id="kw" name="kw" placeholder="Liste-as em sequência, separadas por vírgulas."><?= $modoEdicao ? htmlspecialchars($dados['kw'] ?? '') : '' ?></textarea>
				</div>										
			</div>
			
		</div>
		
		<?php if ($postExists == 1) { ?>
			<input type="hidden" name="id" id="id" value="<?= $id; ?>" />
		<?php } ?>
		
		<div onclick="save();" class="text-ellipsis cm-pad-10 large-12 medium-12 small-12 w-color-bl-to-or pointer w-bkg-wh-to-gr w-shadow w-rounded-15 cm-mg-20-t">
			<span class="fa-stack orange" style="vertical-align: middle;">
				<i class="fas fa-circle fa-stack-2x light-gray"></i>
				<i class="fas fa-save fa-stack-1x fa-inverse dark"></i>					
			</span>						
			Salvar											
		</div>	
	</div>
	<div class="display-none" id="result"></div>
	<script>
	(function () {
		'use strict';                            
		
		let editorInstance = null;
		let editorLoaded = false;	

		function save(){
			formValidator2('divForm', 'core/backengine/wa0006/save.php?type=<?= $tipo ?>', 'result');
		}
		window.save = save;

	})();
	</script>
<?php
}
?>