<header>
    <!-- suneditor -->
    <link rel="stylesheet" href="app/core/backengine/wa0006/suneditor/dist/css/se_viewer.css" />
</header>
<?php 
function shuffle_assoc($list){
	if (!is_array($list)) return $list; 
	$keys = array_keys($list); 
	shuffle($keys); 
	$random = array(); 
	foreach ($keys as $key) { 
	$random[$key] = $list[$key]; 
	}
	return $random; 
} 

date_default_timezone_set('America/Sao_Paulo');
$now = date('Y-m-d H:i:s');
$pc = 0;

//ATRIBUI UM VALOR À PRIVACIDADE DO CONTEÚDO (0 = NÃO MOSTRA / 1 = MOSTRA)
$member = 0;
if(!empty($post['cm'])){
	//Equipe
	$privacy = search('cmp', 'teams', 'pg,pc,em', 'id="'.$post['cm'].'"')[0];
	if(!empty($_SESSION['wz'])){
		$member = 0;
		$teamer = search('cmp', 'teams_users', 'id,us', 'cm="'.$post['cm'].'" AND us="'.$_SESSION['wz'].'" AND st > 0');
		$employ = search('cmp', 'employees', 'id', 'em="'.$privacy['em'].'" AND us="'.$_SESSION['wz'].'" AND st > 0 AND nv > 0');			
		if(count($teamer) > 0 && count($employ) > 0){
			$member = 1;
		}			
	}	
}elseif(!empty($post['em'])){
	//Negócio
	$privacy = search('cmp', 'companies', 'pg,pc', 'id="'.$post['em'].'"')[0];
	if(!empty($_SESSION['wz'])){
		$member = count(search('cmp', 'employees', 'id', 'em="'.$post['em'].'" AND us="'.$_SESSION['wz'].'" AND st > 0 AND nv > 0'));
	}	
}else{
	//Usuário
	$privacy = search('hnw', 'hus', 'pg,pc', 'id="'.$post['us'].'"')[0];
	if(!empty($_SESSION['wz'])){
		$member = count(search('hnw', 'usg', 's0', "s0 = '".$_SESSION['wz']."' AND s1 = '".$post['us']."'"));		
	}	
}

$visibility = 0;

if($post['st'] > 0){	
	if(!empty($_SESSION['wz'])){
	//EXISTE SESSÃO DE USUÁRIO
		if($privacy['pg'] > 0){
		//EXIBIÇÃO DA PÁGINA ESTÁ ATIVADA (PG = 1 ou PG = 2)
			if($privacy['pc'] >= 2){
			//PRIVACIDADE DAS PUBLICAÇÕES É A DE "USUÁRIOS LOGADOS" OU "TODOS"
				$visibility = 1;
			}elseif($privacy['pc'] == 1 && $member > 0){
			//PRIVACIDADE DAS PUBLICAÇÕES É A DE "SEGUIDORES" E "USUÁRIO LOGADO" É "SEGUIDOR" (CONTAGEM = 1)
				$visibility = 1;
			}elseif($privacy['pc'] == 0 && ($post['us'] == $_SESSION['wz'])){
			//PRIVACIDADE DAS PUBLICAÇÕES É A DE "SOMENTE EU" E "USUÁRIO LOGADO" É O "AUTOR"
				$visibility = 1;
			}
		}
	}else{
	//NÃO EXISTE SESSÃO DE USUÁRIO
		if($privacy['pg'] == 2){
		//EXIBIÇÃO DA PÁGINA ESTÁ ATIVADA (PG = 2)
			if($privacy['pc'] == 3){
			//PRIVACIDADE DAS PUBLICAÇÕES É A DE "TODOS"
				$visibility = 1;
			}			
		}
	}
}

if($post['tp'] == 5){
	$visibility = 1;
}

if($visibility == 1){

	if($post['cm'] != 0){
		$tp = 'c';
		$pg = $post['cm'];	
		if($communityData['pc'] == 0 && !isset($_SESSION['wz'])){
			$pc = 0;
		}elseif($communityData['pc'] == 0 && isset($_SESSION['wz']) && in_array($_SESSION['wz'], $mba_us)){
			$pc = 1;
		}elseif($communityData['pc'] == 1){
			$pc = 1;
		}
	}else{
		$tp = 'p';
		$pg = $post['us'];	
		$pc = 1;
	}

	$tp = $post['tp'];

	if(($tp == 1 || $tp == 2) && $pc == 1){
		
		//CABEÇALHO
		?>
		<div class="column large-12 medium-12 small-12 <?php  if($mobile == 1){ ?>cm-pad-15-h<?php  } ?>" style="padding-top: 135px;">	
			<div class="large-12 cm-mg-20-b">
				<h1 title="<?php  echo $post['tt']; ?>"><?php  echo $post['tt']; ?></h1>
			</div>		
		</div>
		<?php 
		//IMAGEM
		if($tp <> 3){
			if($post['im'] <> ''){
				if(substr($post['im'],0,15) == '/uploads/posts/'){ 
					$post['im'] = 'https://workz.com.br'.$post['im'];
				}else{
					$post['im'] = base64_decode($post['im']);
				}			
				?>			
				<div class="column section-box-main-full cm-mg-30-b cm-pad-15-h">			
					<img class="w-shadow background-white w-rounded-20 section-box-main-full w-shadow-1 background-dark border-none" src="<?php  echo $post['im']; ?>" style="object-fit: cover; object-position: center;"/>
				</div>						
				<?php 
			}		
		//APREENTAÇÃO DE SLIDES
		}elseif($tp == 3){
		?>
		<div class="column large-9 cm-mg-30-b">
			<div id="myDiv" class="slide_container w-shadow w-rounded-10 position-relative">			
				<div style="height: 30px; width: 30px; z-index: 999;" title="Maximizar / Minimizar" class="cm-mg-15 w-shadow text-center float-right w-circle pointer w-rounded-5 cm-pad-5-t cm-pad-5-b border-none background-white w-shadow w-color-bl-to-or" onclick="toggleFullScreen(document.getElementById('myDiv'))" id="show_hide_bt">
					<i class="far fa-window-maximize"></i>
				</div>
				<iframe class="w-rounded-10" src="backengine/editor_c.php?pl=<?php  echo $post['id']; ?>">
				</iframe>
			</div>
			<script>
			$('button').click(function(e){
				var $div = $('#myDiv');
				$div.toggleClass('fade');
				setTimeout(function(){
					$div.toggleClass('fade');
				}, 150);
			});
			function arata_ascunde(button){
				v = $(button).text().trim();		
				if(v == 'Minimizar'){
					$(button).html('Maximizar');
				}else{
					$(button).html('Minimizar');
				}
			}
			function toggleFullScreen(elem){
				if((document.fullScreenElement !== undefined && document.fullScreenElement === null) || (document.msFullscreenElement !== undefined && document.msFullscreenElement === null) || (document.mozFullScreen !== undefined && !document.mozFullScreen) || (document.webkitIsFullScreen !== undefined && !document.webkitIsFullScreen)){
					if (elem.requestFullScreen){
						elem.requestFullScreen();
					}else if (elem.mozRequestFullScreen){
						elem.mozRequestFullScreen();
					}else if (elem.webkitRequestFullScreen){
						elem.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
					}else if (elem.msRequestFullscreen){
						elem.msRequestFullscreen();
					}
				}else{
					if (document.cancelFullScreen){
						document.cancelFullScreen();
					}else if (document.mozCancelFullScreen){
						document.mozCancelFullScreen();
					}else if (document.webkitCancelFullScreen){
						document.webkitCancelFullScreen();
					}else if (document.msExitFullscreen){
						document.msExitFullscreen();
					}
				}
			}
			</script>
		</div>
		<?php 
		}
		if($tp <> 3){
		?>
		<div class="column large-9 cm-mg-30-b">
			<div class="large-12 medium-12 small-12 break-word sun-editor-viewer fs-e <?php  if($mobile == 1){ ?> cm-pad-5-h <?php  }else{?> cm-pad-30 w-rounded-20 background-white w-shadow-1 <?php  } ?>">
				<?php 
				$ct = $post['ct'];
				//ARTIGO
				if($tp == 1 || $tp == 2){				
					//print_r($post);
					echo bzdecompress(base64_decode($ct));				
				//FORMULÁRIO
				}elseif($tp == 8){
					$checkboxes = array();
					$content = bzdecompress(base64_decode($ct));
					$questions = explode('(-*question*-)', explode('(-*color*-)', explode('(-*content*-)', $content)[1])[0]);
					$options = explode('(-*color*-)', explode('(-*content*-)', $content)[1])[1];
					
					if(isset($_SESSION['wz']) && (($_SESSION['wz'] == $post['us']) || $_SESSION['wz'] == 1)){
						$hpl_form_answers = $hnw->prepare("SELECT * FROM `hpl.forms.answers` WHERE pl = '".$post['id']."'");
						try{
							$hpl_form_answers->execute();
							$answers_count = $hpl_form_answers->rowCount(PDO::FETCH_ASSOC);
							//RESPOSTAS
							?>
							<div id="aba2" class="display-none">
								<button onclick="abas()" class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-left pointer w-shadow cm-mg-20-b" title="Enviar">Voltar</button>							
								<?php 
								if($answers_count > 0){
								?>
								<button onclick="tableToExcel('workzForm', '<?php  echo $post['tt']; ?>')" class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-right pointer w-shadow cm-mg-20-b" title="Enviar">Exportar em Excel</button>
								<hr>
								<div style="overflow: auto;">
									<table id="workzForm">
										<tr>
										<?php 												
										$n_questions = 0;
										foreach($questions as $topics){
											if(strpos($topics, '(-*text*-)') !== false){
												$card_question = ($n_questions).' - '.explode('(-*text*-)', $topics)[0].'<br>';
											}elseif(strpos($topics, '(-*option*-)') !== false){
												$card_question = ($n_questions).' - '.explode('(-*option*-)', $topics)[0].'<br>';
											}elseif(strpos($topics, '(-*checkbox*-)') !== false){
												$card_question = ($n_questions).' - '.explode('(-*checkbox*-)', $topics)[0].'<br>';
											}elseif(strpos($topics, '(-*date*-)') !== false){
												$card_question = ($n_questions).' - '.explode('(-*date*-)', $topics)[0].'<br>';
											}else{
												$card_question = '';
											}
											?>
											<th class="text-left text-ellipsis" style="width: 200px;"><?php  echo $card_question; ?></th>
											<?php 
											$n_questions++;
										}
										?>
										</tr>
										<?php 
										while($answers_result = $hpl_form_answers->fetch(PDO::FETCH_ASSOC)){
											$ct = bzdecompress(base64_decode($answers_result['ct']));
											$respuestas = explode('(*-answer*-)', $ct);
											$nresp = array();
											$cresp = array();
											foreach($respuestas as $key => $respuesta){
												$nresp[] = explode('(*-number*-)', $respuesta)[0];
												$cresp[] = explode('(*-number*-)', $respuesta)[1];
											}
											?>
											<tr class="text-right">
											<?php 
											for($nq = 0; $nq < $n_questions; $nq++){
												//VERIFICA SE HÁ RESPOSTA PARA A QUESTÃO	
												if($nq > 0){
													if(strpos($ct, $nq.'(*-number*-)') !== false){										
														?>
														<td class="background-gray w-rounded-5 cm-pad-5 text-ellipsis" style="width: 200px;"><?php  echo $cresp[array_search($nq, $nresp)]; ?></td>
														<?php 									
													}else{
														?>
														<td class="background-gray w-rounded-5 cm-pad-5 text-ellipsis" style="width: 200px;"></td>
														<?php 
													}
												}else{
													?>
													<td class="background-gray w-rounded-5 cm-pad-5 text-left text-ellipsis" style="width: 200px;"><?php  echo $answers_result['ml']; ?></td>
													<?php 
												}		
											}
											?>								
											</tr>
											<?php 
										}
										?>
									</table>
								</div>
								<?php 
								}else{
								?>
								<hr>
								<div class="w-rounded-5 large-12 medium-12 small-12 cm-pad-10 cm-mg-10-t font-weight-600 fs-a uppercase" style="background: #F7F8D1;">
									<i class="fas fa-info-circle cm-mg-5-r"></i>Não há respostas para este formulário.
								</div>
								<?php 
								}
								?>
							</div>
							<?php 						
						}catch(Exception $e){
							echo $e->getMessage();
						}				
					}
					?>
					<div id="aba1" class="position-relative">
						<div class="large-12 medium-12 small-12 cm-mg-30-b">
							<div class="w-post-content-city">
								<?php  echo $post['ci']; ?>
							</div>
							<a <?php  if(isset($_SESSION['wz']) && (($_SESSION['wz'] == $post['us']) || $_SESSION['wz'] == 1)){ ?> id="" <?php  } ?>><?php  echo nl2br($questions[0]); ?></a>
						</div>
						<form id="sectionForm" action="backengine/hpl_form_answer.php" method="POST" target="formFrame">
						<input type="hidden" name="hpl" value="<?php  echo $post['id']; ?>"></input>
						<?php 
						$i = 0;					
						unset($questions[0]);					
						if(strpos($options, '(-*shuffleQuestions*-)') !== false){						
							$questions = shuffle_assoc($questions);
						}									
						foreach($questions as $key => $question){					
						?>										
						<div id="" class="large-12 medium-12 small-12 w-rounded-10 w-shadow cm-pad-20 cm-mg-30-b">						
							<?php 		
							$required = 0;
							if(strpos($question, '(-*text*-)') !== false){
								if(strpos($question, '(-*required*-)') !== false){
									$required = 1;
									$question = str_replace('(-*required*-)','',$question);
								}
								$card_question = explode('(-*text*-)', $question)[0];
								?>					
								<div class="large-12 medium-12 small-12 cm-mg-20-b font-weight-600"><?php  echo strip_tags($card_question); if($required == 1){ ?> <a class="orange">*</a><?php  } ?></div>
								<textarea class="large-12 medium-12 small-12 border-like-input w-rounded-5 cm-pad-10" placeholder="Sua resposta" name="<?php  echo $key; ?>_text" <?php  if($required == 1){ ?> required <?php  } ?>></textarea>
								<?php 
							}elseif(strpos($question, '(-*option*-)') !== false){
								if(strpos($question, '(-*required*-)') !== false){
									$required = 1;
									$question = str_replace('(-*required*-)','',$question);
								}
								$card_question = explode('(-*option*-)', $question)[0].'<br>';
								$card_options = explode('(-*correct*-)', explode('(-*option*-)', $question)[1])[0];
								$card_correct = explode('(-*correct*-)', explode('(-*option*-)', $question)[1])[1];					
								?>
								<div class="large-12 medium-12 small-12 cm-mg-20-b font-weight-600"><?php  echo strip_tags($card_question); if($required == 1){ ?> <a class="orange">*</a><?php  } ?></div>
								<?php 
								$answers = explode(',', $card_options);
								if(strpos($options, '(-*shuffleQuestions*-)') !== false){
									shuffle($answers);
								}
								foreach($answers as $option){
								?>			
								<input id="radio_<?php  echo $i.'_'.$option; ?>" name="<?php  echo $key; ?>_radio" type="radio" value="<?php  echo $option; ?>" <?php  if($required == 1){ ?> required <?php  } ?>></input><label class="cm-mg-10-l" for="radio_<?php  echo $i.'_'.$option; ?>"><?php  echo $option; ?></label><br>
								<?php 
								}					
							}elseif(strpos($question, '(-*checkbox*-)') !== false){													
								if(strpos($question, '(-*required*-)') !== false){
									$required = 1;
									$question = str_replace('(-*required*-)','',$question);								
									$checkboxes[] = $i;							
								}
								$card_question = explode('(-*checkbox*-)', $question)[0].'<br>';
								$card_options = explode('(-*correct*-)', explode('(-*checkbox*-)', $question)[1])[0];
								$card_correct = explode('(-*correct*-)', explode('(-*checkbox*-)', $question)[1])[1];					
								?>
								<div class="large-12 medium-12 small-12 cm-mg-20-b font-weight-600"><?php  echo strip_tags($card_question); if($required == 1){ ?> <a class="orange">*</a><?php  } ?></div>							
								<?php 
								$answers = explode(',', $card_options);
								if(strpos($options, '(-*shuffleQuestions*-)') !== false){
									shuffle($answers);
								}
								foreach($answers as $checkbox){
								?>
								<input id="checkbox_<?php  echo $i.'_'.$checkbox; ?>" name="<?php  echo $key; ?>_checkbox[]" class="checkbox_<?php  echo $i; ?>" type="checkbox" value="<?php  echo $checkbox; ?>"></input><label class="cm-mg-10-l" for="checkbox_<?php  echo $i.'_'.$checkbox; ?>"><?php  echo $checkbox; ?></label><br>
								<?php 
								}						
							}elseif(strpos($question, '(-*date*-)') !== false){
								$card_question = explode('(-*date*-)', $question)[0].'<br>';
								if(strpos($question, '(-*required*-)') !== false){
									$required = 1;
									$question = str_replace('(-*required*-)','',$question);
								}
								?>
								<div class="large-12 medium-12 small-12 cm-mg-20-b font-weight-600"><?php  echo strip_tags($card_question); if($required == 1){ ?> <a class="orange">*</a><?php  } ?></div>
								<input name="<?php  echo $key; ?>_date" type="date" class="large-12 medium-12 small-12 border-like-input w-rounded-5 cm-pad-5" <?php  if($required == 1){ ?> required <?php  } ?>></input>
								<?php 
							}
							?>						
						</div>					
						<?php 
						$i++;
						}
						if(strpos($options, '(-*getMail*-)') !== false){
						?>
						<div class="clearfix large-12 medium-12 small-12 cm-mg-30-b">
							<div class="large-3 medium-3 small-12 float-left cm-pad-20-r uppercase font-weight-600 fs-b">
								<label>E-mail*</label>
							</div>
							<div class="large-9 medium-9 small-12 float-right">
								<input class="w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10" id="email" name="email" type="email" placeholder="seu_email@email.com.br" required></input>
							</div>							
						</div>
						<?php 
						}
						?>
						<div class="clearfix large-12 medium-12 small-12">
							<?php 
							if(isset($_SESSION['wz']) && (($_SESSION['wz'] == $post['us']) || $_SESSION['wz'] == 1)){
							?>
							<button type="submit" onclick="abas()" class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-right pointer w-shadow" title="Enviar">Respostas</button>
							<?php 
							}else{
							?>	
							<iframe class="border-none large-8 medium-8 small-12 cm-mg-5-t" style="height: 40px;" name="formFrame"></iframe>						
							<button type="submit" class="w-rounded-5 w-form-button large-3 medium-3 small-12 float-right pointer w-shadow" title="Enviar">Enviar</button>						
							<?php 
							}
							?>							
						</div>
						</form>					
					</div>	
					<?php 				
					foreach($checkboxes as $ckb){
					?>
					<script>
						(function() {
							const form = document.querySelector('#sectionForm');
							var checkboxes<?php  echo $ckb; ?> = form.querySelectorAll('input[class=checkbox_<?php  echo $ckb; ?>]');
							var checkboxLength<?php  echo $ckb; ?> = checkboxes<?php  echo $ckb; ?>.length;
							var firstCheckbox<?php  echo $ckb; ?> = checkboxLength<?php  echo $ckb; ?> > 0 ? checkboxes<?php  echo $ckb; ?>[0] : null;
							function init<?php  echo $ckb; ?>(){
								if (firstCheckbox<?php  echo $ckb; ?>) {
									for (let i = 0; i < checkboxLength<?php  echo $ckb; ?>; i++) {
										checkboxes<?php  echo $ckb; ?>[i].addEventListener('change', checkValidity);
									}
									checkValidity();
								}
							}
							function isChecked() {
								for (let i = 0; i < checkboxLength<?php  echo $ckb; ?>; i++) {
									if (checkboxes<?php  echo $ckb; ?>[i].checked) return true;
								}
								return false;
							}
							function checkValidity(){
								const errorMessage = !isChecked() ? 'Pelo menos uma caixa deve ser selecionada' : '';
								firstCheckbox<?php  echo $ckb; ?>.setCustomValidity(errorMessage);
							}
							init<?php  echo $ckb; ?>();
						})();
					</script>
					<?php 	
					}			
					?>
					<script>					
						function abas(){					
							var aba1 = document.getElementById('aba1');
							var aba2 = document.getElementById('aba2');						
							if(aba1.style.display == 'none'){							
								aba1.style.display = 'block';
								aba2.style.display = 'none';							
							}else{							
								aba2.style.display = 'block';
								aba1.style.display = 'none';							
							}						
						}
					</script>
					<?php 
				}
				?>
				<script src='js/autosize.js'></script>
				<script>
					autosize(document.querySelectorAll('textarea'));
				</script>
			</div>
		</div>
		<?php 
		}
		?>
		<div class="column large-3 cm-mg-15-b">
			<div class=" <?php  if($mobile == 1){ ?> cm-pad-5-h <?php  }else{?> background-white w-rounded-20 w-shadow-1 cm-pad-20 <?php  } ?>">					
			<div onclick="shareContent()" class="text-right large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-b w-color-bl-to-or pointer">
				
				<label title="Ajustes"  class="pointer">
					<a class="font-weight-500" style="vertical-align: middle;"> Compartilhar</a>									
				</label>
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-share fa-stack-1x fa-inverse dark"></i>					
				</span>
			</div>
			<div class="clearfix text-right <?php  if(($config <> '') && (isset($config[2]) && $config[2] <> '')){ ?> w-rounded-10 <?php  } ?>">
				<div class="">
				
					<p>Por <a class="w-color-bl-to-or font-weight-600" href="https://workz.com.br?profile=<?php  echo $post['us']; ?>"><?php  echo $postUser; ?></a>
					<?php 
					if($post['cm'] <> 0){
					?>
					em <a class="w-color-bl-to-or font-weight-600" href="https://workz.com.br?team=<?php  echo $post['cm']; ?>"><?php  echo $communityData['tt']; ?></a>	
					<?php 
					}
					?>
					</p>
				</div>
				<div class="">
					Publicado em <?php  echo date('d/m/Y', strtotime($post['dt'])); ?>, às <?php  echo date('H:i', strtotime($post['dt'])); ?>
				</div>
				<?php 		
				if(($post['cm'] != 0 && $communityData['pc'] == 0) || $post['cm'] == 0){
				?>									
						
				<?php 
				//REGISTRA O IP DO LEITOR
				$ip = $_SERVER['HTTP_CLIENT_IP'] ?? 
					  $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
					  $_SERVER['REMOTE_ADDR'];

				if ($ip !== '') {
					$ip = substr($ip, 0, 100); // segurança básica

					// Limite de 1 visualização por IP a cada 24h
					$limitDate = date('Y-m-d H:i:s', strtotime('-24 hours'));
					$sta = search('hnw', 'sta', '', "ip = '{$ip}' AND pl = '{$post['id']}' AND dt >= '{$limitDate}'");

					if (empty($sta)) {
						insert('hnw', 'sta', 'ip,pl,us,dt', "'{$ip}','{$post['id']}','{$post['us']}','{$now}'");
					}
				}

				// Agora conta total de visualizações (opcional: apenas das últimas 24h, se quiser)
				$views = search('hnw', 'sta', '', "pl = '{$post['id']}'");
				$views_count = count($views);								
				?>
				<div class="cm-mg-10-t">
				<?php 
				if($views_count > 1){
					echo $views_count.' visualizações';
				}else{
					echo $views_count.' visualização';
				}				
				?>
				</div>					
				<?php 			
				}
				?>									
			</div>
			<hr>				
			<?php 	
			if(isset($_SESSION['wz'])){				
				$comments = search('hnw', 'hpl_comments', 'pl,us,ds', "pl = '".$post['id']."'");
				if(count($comments) > 0){					
					$commentator = search('hnw', 'hus', 'un,tt', '')[0];
					?>					
					<div class="large-12 medium-12 small-12 text-ellipsis">
						<a target="_blank" class="w-color-bl-to-or pointer font-weight-600" href="https://workz.com.br/<?php  if($commentator['un'] <> ''){ echo $commentator['un']; }else{ echo '?profile='.$comments[0]['us']; } ; ?>" ><?php  if($commentator['un'] <> ''){ echo $commentator['un']; }else{ echo strtok($commentator['tt'], ' '); } ; ?></a> <a><?php  echo $comments[0]['ds']; ?></a>
					</div>
					<div class="comment large-12 medium-12 small-12 text-ellipsis gray">
						<a class="pointer w-color-bl-to-or font-weight-600" onclick="
						toggleSidebar();
						var config = $('<div id=config class=height-100></div>'); 
						$('#sidebar').append(config); 
						waitForElm('#config').then((elm) => {
							goTo('../partes/resources/modal_content/comments.php', 'config', '', '<?php  echo $post['id']; ?>');
						});								
						">Ver todos os <?php  if(count($comments) > 1){ echo count($comments); } ?> comentários</a>
					</div>
					<?php 
				}else{
					?>
					<a class="comment pointer w-color-bl-to-or font-weight-600"
					onclick="
						toggleSidebar();
						var config = $('<div id=config class=height-100></div>'); 
						$('#sidebar').append(config); 
						waitForElm('#config').then((elm) => {
							goTo('../partes/resources/modal_content/comments.php', 'config', '', '<?php  echo $post['id']; ?>');
						});
					">Seja o primeiro a comentar esta publicação.</a>		
					<?php 
				}	
			}else{
			    ?>
			    <div class="<?php  if($mobile == 1){ ?> cm-pad-15-h <?php  } ?>">
			    <?php 
			    include('partes/login.php');				
			    ?>       
			    </div>
			    <?php 
				
			}			
			?>						
			</div>					
		</div>
		<div class="column large-3 cm-mg-30-b">		
		</div>	
		<?php 
	//SITE
	}elseif($tp == 5  && $pc == 1){
		$data = json_decode(urldecode($post['ct']), true);

		if (!$data) {
			die(json_encode(["message" => "Erro ao decodificar JSON"]));
		}

		// **Sanitizar o CSS (evitando injeção de código malicioso)**
		$css = strip_tags($data["css"]); 

		// **O HTML não precisa ser escapado para ser renderizado**
		//$html = $data["html"];		
		$html = html_entity_decode($data['html'], ENT_QUOTES, 'UTF-8');

		echo "<style>{$css}</style>";
		echo $html;	
	}else{		
		?>
		<div class="column <?php  if(!isset($_SESSION['wz'])){?> large-8 <?php }else{?> large-12 <?php } ?>">
			<div class="w-row">
				<div class="w-rounded-10 w-shadow large-12 medium-12 small-12 cm-pad-20 font-weight-600 fs-a uppercase" style="background: #F7F8D1;">
					<i class="fas fa-info-circle cm-mg-5-r"></i> Conteúdo indisponível.
				</div>
			</div>
		</div>
		<?php 
		include('partes/coluna_direita.php');
	}

}else{
?>
<div class="cm-pad-10-b cm-pad-15-h">
	<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
		<i class="fas fa-info-circle cm-mg-5-r"></i> Conteúdo indisponível.
	</div>
</div>
<?php 
}
?>