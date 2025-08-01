<?php
//header('Location: core/backengine/wa0007/zmail/index.php');	

/*
// Configurando o cabeçalho para permitir a exibição em frames de outro domínio
//header("X-Frame-Options: ALLOW-FROM https://workz.com.br/");
?>
<?
if(session_status() === PHP_SESSION_NONE){
    session_start();
	require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
}

print_r($_GET);
if(isset($_SESSION['wz'])){
	if(!empty($_GET['qt']) && ($_GET['qt'] == 'new')){
	?>
	<div id="tab" class="large-12 medium-12 small-12 overflow-x-hidden position-absolute abs-r-0 abs-b-0 abs-l-0 height-100 border-like-input w-rounded-10 background-white">
		<div class="large-12 medium-12 small-12 cm-pad-15 cm-pad-30-h display-center-general-container position-sticky w-shadow-1 background-gray z-index-1">
			<a class="float-left text-ellipsis" style="width: calc(100% - 32.39px)">Adicionar conta de e-mail</a>
			<div class="float-right fs-c cm-pad-5 cm-pad-0-h">			
				<span onclick="goTo(`core/wa0007.php`, `appContent`, ``, ``);" class="fa-stack pointer w-color-bl-to-or pointer" style="vertical-align: middle;" title="Voltar">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
				</span>			
			</div>
			<div class="clear"></div>
		</div>
		<div class="large-10 medium-12 small-12 centered overflow-x-hidden">										
			<div id="divForm" class="large-12 medium-12 small-12 cm-pad-30">								
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>E-mail*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="ml" name="ml" type="text" placeholder="email@provedor.com.br" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>			
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>Servidor IMAP*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="imap" name="imap" type="text" placeholder="imap.provedor.com.br" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>Servidor SMTP*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="smtp" name="smtp" type="text" placeholder="smtp.provedor.com.br" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>Porta IMAP*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="imap_port" name="imap_port" type="number" placeholder="993" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>Porta SMTP*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="smtp_port" name="smtp_port" type="number" placeholder="465" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-12 medium-12 small-12 cm-mg-20-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
						<label>Senha*</label>
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="required w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="pw" name="pw" type="password" value="" required></input>
					</div>
					<div class="clear"></div>
				</div>
				
				<div onclick="" style="color: <? echo $colours[0]; ?>;" class="float-right w-bkg-tr-gray display-center-general-container cm-pad-10 large-6 medium-6 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c cm-mg-5-b">
					<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">					
						<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
						<i class="fas fa-plus pointer z-index-1 fs-c centered"></i>
					</div>
					<a class="font-weight-500 cm-pad-10-l">Adicionar</a>					
				</div>
				
			</div>
		</div>
	</div>
	<?
	}else{
	?>
	<div id="tab" class="large-12 medium-12 small-12 overflow-x-hidden position-absolute abs-r-0 abs-b-0 abs-l-0 height-100 border-like-input w-rounded-10 background-white">
		<div class="large-12 medium-12 small-12 cm-pad-15 cm-pad-30-h display-center-general-container position-sticky w-shadow-1 background-gray z-index-1">
			<a class="float-left text-ellipsis" style="width: calc(100% - 32.39px)">Meus e-mails</a>
			<div class="float-right fs-c cm-pad-5 cm-pad-0-h">			
				<span onclick="goTo(`core/wa0007.php`, `appContent`, `new`, ``);" class="fa-stack pointer w-color-bl-to-or pointer" style="vertical-align: middle;" title="Adicionar conta de e-mail">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-plus fa-stack-1x fa-inverse"></i>
				</span>			
			</div>
			<div class="clear"></div>
		</div>
		<div class="large-10 medium-12 small-12 centered overflow-x-hidden">							
			<?		
			//HOME
			$myMails = search('app', 'wa0007_regs', '', "us = '".$_SESSION['wz']."'");		
			if(count($myMails) > 0){
			?>
			<div class="overflow-x-auto cm-pad-20-t">
				<div style="min-width: 800px">
					<div class="cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative text-ellipsis border-b-input overflow">
						<div class="float-left large-6 medium-6 small-6 text-ellipsis font-weight-500 cm-pad-20-r">E-mail</div>
						<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-500 cm-pad-20-r">Servidor IMAP</div>
						<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-500 cm-pad-20-r">Servidor SMTP</div>
						<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-500 cm-pad-20-r">Porta IMAP</div>
						<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-500 cm-pad-20-r">Porta SMTP</div>
						<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-500 cm-pad-20-r">Senha</div>
						<div class="clear"></div>
					</div>
					<?						
					foreach($myMails as $myMailsResult){
					?>							
					<label title="<? echo $myMailsResult['ml']; ?> - Clique para Entrar" onclick="" class="pointer">
						<div class="cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input">
							<div class="float-left large-6 medium-6 small-6 text-ellipsis font-weight-400 cm-pad-20-r"><? echo $myPostsResult['ml']; ?></div>
							<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r"><? echo $myMailsResultt['imap']; ?></div>
							<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r"><? echo $myMailsResultt['smtp']; ?></div>
							<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r"><? echo $myMailsResultt['imap_port']; ?></div>
							<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r"><? echo $myMailsResultt['smtp_port']; ?></div>
							<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r"><? echo $myMailsResultt['pw']; ?></div>
							<div class="clear"></div>
						</div>
					</label>
					<?					
					}
				?>
				</div>
			</div>
			<?
			}else{
				?>
				<div class="large-12 medium-12 small-12 text-center cm-pad-30">
					<img src="https://workz.com.br/images/sad.png" /><br />
					<p class="font-weight-600 cm-mg-20-t">Oh não! Você ainda cadastrou nenhuma conta de e-mail.</p>					
				</div>
				<?
			}
			?>
		</div>
	</div>
	<?
	}
}else{
	?>
	Faça o login abaixo
	<?
	include('../partes/login.php');
	?>
	Ou entre no seu e-mail institucional (workz!, bioenergi, protspot, guilhermesantana, weeki)
	<?
	//header('Location: core/backengine/wa0007/zmail/index.php');	
}
*/
?>
</div>