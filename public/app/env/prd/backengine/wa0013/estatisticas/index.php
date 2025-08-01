<?
session_start();
//ProtSpot API URL
$psd_url = "https://www.protspot.com/Login.php";
$psf_url = "https://www.protspot.com/classes/fblogin.php";
$get_url = "https://www.protspot.com/apps/get.php";
//YOUR APP INFO
$app_url = "https://workz.com.br"; //YOUR APP URL - !Important: without "/" in sufix...
$app_id = '1'; //YOUR APP PROTSPOT ID
$app_secret = '50572103'; //YOUR APP PROTSPOT SECRET
$style = 'dark';
?>
<!DOCTYPE html>
<html lang="pt-BR">	
	<head>
		<link rel="shortcut icon" href="../sistemas/images/logo.ico" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0" />
		<meta name="theme-color" content="#222222">
		<meta name="msapplication-navbutton-color" content="#222222">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-capable" content="yes" />		
		<meta name="description" content="Moedas e indicadores financeiros para cálculo de empréstimos ou investimentos">
		<meta name="keywords" content="moeda,contratual,moedas,contratuais,cdi,ipca,série,ipca,ipca,excel,tlp,urtjlp,tjlp,tlp,rfipca,rf,umipca,pre,314,321,311,019,184,185,777,ibge,bndes,360,365,366">
		<title>Workz! | Moedas e Indicadores</title>		
		<meta property="og:url" content="<? echo 'https://workz.com.br/estatisticas'; ?>" />
		<meta property="og:type" content="website" />
		<meta property="og:title" content='Workz! | Moedas e Indicadores' />		
		<meta property='og:image' content='https://workz.com.br/images/icons/workz/196x196.png'/>
		<meta property="og:description" content='Atualização automática de CDI, IPCA e algumas moedas contratuais do BNDES' />
		
		<!-- CSS -->
		<link href="../RequestReducedStyle.css" rel="Stylesheet" type="text/css" />
		<link href="../cm-pad.css" rel="Stylesheet" type="text/css" />
		<link href="../sizes.css" rel="Stylesheet" type="text/css" />
		<!-- FONT AWESOME -->
		<script defer src="https://use.fontawesome.com/releases/v5.7.2	/js/all.js" integrity="sha384-0pzryjIRos8mFBWMzSSZApWtPl/5++eIfzYmTgBBmXYdhvxPc+XcFEk+zJwDgWbP" crossorigin="anonymous"></script>
		<link rel="stylesheet" href="vendors/iconfonts/mdi/css/materialdesignicons.min.css">		
		<!-- JQUERY -->
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<script src="async/funcs.js"></script>	
		<!-- SOCIAL SHARE -->
		<script language="javascript">
		//SOCIAL MEDIA
		function fbshareCurrentPage(){
			window.open("https://www.facebook.com/sharer/sharer.php?u="+escape(window.location.href)+"&t="+document.title, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=300,width=600');return false;
		}
		function wtshareCurrentPage(){
			window.open("whatsapp://send?text="+escape(window.location.href)+"");return false;
		}
		function trshareCurrentPage(){
			window.open("https://twitter.com/share?url="+escape(window.location.href)+"");return false;
		}
		function lkshareCurrentPage(){
			window.open('https://www.linkedin.com/cws/share?url=' +escape(window.location.href)+ '?name=' +'Workz', 'newwindow', 'width=680, height=450');
		}
		</script>		
	</head>
	<body>
		<div id="fb-root"></div>
		<script async defer crossorigin="anonymous" src="https://connect.facebook.net/pt_BR/sdk.js#xfbml=1&version=v5.0"></script>
		<script type="text/javascript">
			//Exporta xls
			var tableToExcel = (function() {
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
		<div class="off-canvas-wrapper">
			<?
			include('../partes/barra_superior.php');
			?>
			<div class="off-canvas-wrapper-inner <?if($mobile == 0){?>off-canvas-wrapper-inner-height-desktop<?}else{?>off-canvas-wrapper-inner-height-mobile<?}?>">
				<div class="row">
					<div class="cm-mg-110-t">
						<div class="large-12">
							<div class="columns large-8">
								<div class="row w-row">
									<div class="column large-12">
										<div class="w-rounded-10 background-white cm-pad-20-t cm-pad-20-h cm-pad-10-b large-12 w-shadow cm-mg-30-b position-relative">											
											<h4 class="fs-c uppercase border-b-brown-3 cm-pad-20-b cm-mg-20-b cm-pad-5-t"><div class="w-icon-or"><i class="fas fa-chart-bar"></i></div> Moedas e Indicadores</a></h4>
											<div id="start" class="w-community-container">												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('CDI')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
															<div class="large-12 medium-12 small-12">
																<a class="font-weight-600">CDI</a>
																<hr>
															</div>	
															<a class="fs-a gray">Taxa anual e fator de atualização, atualizados diariamente (dias úteis).</a>
														</div>
													</div>
												</div>												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('IPCAIBGE')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">IPCA <small>(XLS)</small></a>
																<hr>
																<a class="fs-a gray">Séries IPCA divulgadas mensalmente pelo IBGE, em formato XLS.</a>
															</div>													
														</div>
													</div>
												</div>												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('SERIE_IPCA')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">IPCA <small>(SÉRIE)</small></a>
																<hr>
																<a class="fs-a gray">Série IPCA divulgada pelo IBGE, com o fator mensal.</a>
															</div>	
														</div>														
													</div>
												</div>
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('IPCA_ANBIMA_2Q')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">IPCA <small>(2ªQ PROJETADO)</small></a>
																<hr>
																<a class="fs-a gray">Série IPCA de 2ª Quinzena, projetado pela Anbima.</a>
															</div>	
														</div>														
													</div>
												</div>
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('URTJLP')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">URTJLP <small>(314)</small></a>
																<hr>
																<a class="fs-a gray">URTJLP 360 dias.</a>
															</div>	
														</div>														
													</div>
												</div>												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('URTJLP_360_365')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">URTJLP <small>(321)</small></a>
																<hr>
																<a class="fs-a gray">URTJLP 365/366 dias.</a>
															</div>	
														</div>														
													</div>
												</div>												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('TJLP')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">TJLP <small>(311)</small></a>
																<hr>
																<a class="fs-a gray">TJLP divulgada trimestralmente pelo BC.</a>														
															</div>	
														</div>														
													</div>
												</div>											
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('IPCAAC')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">RFIPCA <small>(019)</small></a>
																<hr>
																<a class="fs-a gray">Indicador de referência fixa em contratos do BNDES em IPCA</a>
															</div>	
														</div>														
													</div>
												</div>												
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('UMIPCA')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">UMIPCA <small>(184)</small></a>
																<hr>
																<a class="fs-a gray">Unidade Monetária de IPCA, atualizado mensalmente.</a>														
															</div>	
														</div>														
													</div>
												</div>
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('IPCA_TLP')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>														
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">IPCA TLP <small>(185)</small></a>
																<hr>
																<a class="fs-a gray">Moeda de atualização monetária em contratos BNDES em TLP.</a>
															</div>	
														</div>														
													</div>
												</div>
												<div class="large-3 medium-2 small-4 cm-pad-10 float-left w-color-bl-to-or" onclick="document.getElementById('start').style.display='none'; document.getElementById('mod1').style.display='block'; asyncdb('TLP_PRE')">													
													<div class="w-rounded-10 border-like-input large-12 w-square position-relative w-bkg-tr-gray">
														<div>
															<div class="w-rounded-10 w-square-content w-shadow pointer dark line-clamp overflow-hidden" style="border: 20px solid transparent;">
																<a class="font-weight-600">TLP PRÉ <small>(777)</small></a>
																<hr>
																<a class="fs-a gray">Indicador de referência fixa em contratos do BNDES em TLP</a>
															</div>																
														</div>														
													</div>
												</div>
												<div class="clear"></div>
											</div>
											<div id="mod1" class="start">
												<div id="resultado"></div>
											</div>											
										</div>
									</div>
								</div>
							</div>
							<!-- COLUNA DIREITA -->
							<div class="columns large-4">
								<div class="row">
									<div class="columns large-12 medium-12 small-12">
										<?
										if(!isset($_SESSION['wz'])){
										?>
										<!-- LOGIN PROTSPOT -->
										<div class="w-rounded-10 background-white cm-pad-20-t cm-pad-20-h cm-pad-10-b w-shadow cm-mg-30-b">
											<div class="large-12 cm-pad-20-b">
												<h2 class="fs-c uppercase border-b-brown-3 cm-pad-10-b cm-pad-5-t"><img class="cm-mg-10-r" style="height: 24px; width: 24px;" src="https://protspot.com/images/ps_ico.png" /> Entrar</h2>
											</div>
											<div class="w-form">
												<form action="<? echo $psd_url; ?>" method="POST">
													<input type="hidden" name="app_url" id="app_url" value="<? echo $app_url; ?>/protspot/access.php"></input>
													<input type="hidden" name="app_id" id="app_id" value="<? echo $app_id; ?>"></input>
													<input type="hidden" name="app_secret" id="app_secret" value="<? echo $app_secret; ?>"></input>						
													<div class="form-group">
														<label for="user-mail">E-mail</label>								
														<input class="w-rounded-5 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-like-input" type="email" name="user_mail" id="user_mail" placeholder="E-mail">
													</div>						
													<div class="form-group">
														<label for="user-pass">Senha</label>								
														<input class="w-rounded-5 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-like-input" type="password" style="width: 100%;" name="user_pass" id="user_pass" placeholder="Senha">
													</div>							
													<small class="float-right cm-mg-5-b"><a class="w-color-bl-to-or" onclick="reset_password(document.getElementById('user_mail').value);" style="cursor: pointer;">Esqueci minha senha</a></small>
													<input title="Clique para entrar" class="w-rounded-5 cm-mg-15-b cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 font-weight-600 pointer w-all-or-to-bl w-shadow border-none fs-c" type="submit" style="" class="input-shadow" value="ENTRAR / REGISTRAR-SE"></input>
												</form>					
												<div class="clear"></div>
												<?
												/*
												<form action="<? echo $psf_url; ?>" method="POST">							
													<input type="hidden" name="app_url" id="app_url" value="<? echo $app_url.'/protspot/access.php'; ?>"></input>
													<input type="hidden" name="app_id" id="app_id" value="<? echo $app_id; ?>"></input>
													<input type="hidden" name="app_secret" id="app_secret" value="<? echo $app_secret; ?>"></input>
													<input type="submit" name="fblogin" id="fblogin" value="ENTRAR COM FACEBOOK" class="w-rounded-5 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 font-weight-600 pointer w-shadow border-none fs-c white" style="background-color: #4267B2;"></input>						
												</form>						
												*/
												?>					
												<script>
												function reset_password(mail){
													if(mail == ''){
														alert('Insira o seu e-mail no campo correspondente e, então, clique novamente em "Esqueci minha senha".');
													}else{
														window.open('https://protspot.com/renew_password.php?user_mail=' + mail, '_blank');
													}
												}								
												</script>
											</div>				
										</div>
										<?
										}
										/*
										?>										
										<div class="w-rounded-10 background-white cm-pad-20-t cm-pad-20-h cm-pad-10-b w-shadow cm-mg-30-b text-center">										
											<iframe src="https://br.widgets.investing.com/live-currency-cross-rates?theme=lightTheme&hideTitle=true&roundedCorners=true&pairs=1617,1736,1890,2103" width="100%" height="290px" frameborder="0" allowtransparency="true" marginwidth="0" marginheight="0"></iframe>											
										</div>
										<?
										*/
										?>
										<div class="fb-comments" data-href="<? echo "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>" data-numposts="5" data-width="100%"></div>		
										<ul class="social text-left cm-mg-30-b text-right">
											<li class="cm-mg-5-r pointer">
												<a onclick="fbshareCurrentPage()" target="_blank" alt="Share on Facebook">
													<img src="https://workz.com.br/images/icons/icon_fb.png" />
												</a>
											</li>
											<li class="cm-mg-5-r pointer">
												<a onclick="lkshareCurrentPage()">
													<img src="https://workz.com.br/images/icons/icon_in.png" />
												</a>
											</li>
											<li class="cm-mg-5-r pointer">
												<a onclick="trshareCurrentPage()">
													<img src="https://workz.com.br/images/icons/icon_tr.png" />
												</a>
											</li>
											<li class="show-for-small-only pointer">
												<a onclick="wtshareCurrentPage()" data-action="share/whatsapp/share">
													<img src="https://workz.com.br/images/icons/icon_wt.png" />
												</a>
											</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<? include('../partes/rodape.php'); ?>	
		</div>
		<script>
		function myFunction() {
			/* Get the text field */
			var copyText = document.getElementById("select");

			/* Select the text field */
			copyText.select();
			copyText.setSelectionRange(0, 99999); /*For mobile devices*/

			/* Copy the text inside the text field */
			document.execCommand("copy");

			/* Alert the copied text */
			alert("Copiado para área de transferência");
		}
		</script>
	</body>	
</html>