<div class="cm-pad-15-h large-3-5 medium-4 small-12 flex hide-for-small-only" style="flex-direction: column;">
	<!-- PAGE IMAGE -->
	<div class="<?php if($mobile == 0){?>large-12 medium-12 small-12<?php }else{?>large-3 medium-3 small-3<?php }?>	">
		<div class="large-12 w-square position-relative">											
			<div class="w-circle w-square-content position-relative border-div-gray" style="background: url(data:image/jpeg;base64,<?php  if(!empty($_GET)){ echo $pgim; }else{ echo $loggedUser['im']; } ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />
			</div>																					
		</div>
	</div>	
	<div class="cm-mg-25-t background-white cm-pad-15 w-shadow-1 w-rounded-20 height-tr" style="flex-grow: 1;">
		<?php 
		if(!empty($_GET) && isset($_SESSION['wz'])){
			if(isset($_GET['team']) || isset($_GET['company'])){				
				if($user_level === ''){
				?>							
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>					
					</span>				
					<a onclick="pageJoin('insert')" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Solicitar acesso</a>
				</div>
				<?php 
				}elseif($user_level === 0){
				?>								
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-clock fa-stack-1x fa-inverse dark"></i>					
					</span>																	
					<a class="font-weight-500 dark" style="vertical-align: middle;"> Aguardando</a>									
				</div>
				<?php 
				}elseif($user_level > 2){
				?>								
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-cog fa-stack-1x fa-inverse dark"></i>					
					</span>
					<label id="pageConfig" title="Ajustes" onclick="
							toggleSidebar(); 
							var config = $('<div id=config class=height-100></div>'); 
							$('#sidebar').append(config); 
							goTo('partes/resources/modal_content/config_home.php', 'config', '<?php  echo $pgid.'&op=0'; ?>', '<?php  echo key($_GET); ?>');"	
					class="pointer">
						<a class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;"> Ajustes</a>									
					</label>
				</div>
				<?php 
				}
				if(!empty($user_level) && $user_level > 0 && $user_level < 3){
				?>
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-unlink fa-stack-1x fa-inverse dark"></i>					
					</span>				
					<a onclick="pageJoin('delete')" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;" title="Deixar de ser membro"> Desvicular-se</a>
				</div>				
				<?php 
				}				
				?>
				<script>
				function pageJoin(action) {
					const params = new URLSearchParams(window.location.search);
					const firstKey = Array.from(params.keys())[0];
					const firstValue = params.get(firstKey);

					const pdo_params = {
						type: action,
						db: 'cmp'
					};

					if (firstKey === 'team') {
						pdo_params.table = 'teams_users';
					} else if (firstKey === 'company') {
						pdo_params.table = 'employees';
					}

					let swalTitle = '';
					let swalText = '';

					if (action === 'delete') {
						if (firstKey === 'team') {
							pdo_params.where = 'us="<?= $_SESSION['wz'] ?>" AND cm="' + firstValue + '"';
							swalTitle = 'Sair desta equipe?';
						} else if (firstKey === 'company') {
							pdo_params.where = 'us="<?= $_SESSION['wz'] ?>" AND em="' + firstValue + '"';
							swalTitle = 'Sair deste negócio?';
						}
						swalText = 'Esta ação terá impacto em sua tela inicial e poderá afetar a exibição de registros em alguns aplicativos.';
					} else if (action === 'insert') {
						pdo_params.values = `"${firstValue}", "<?= $_SESSION['wz'] ?>", "<?= date('Y-m-d H:i:s') ?>"`;

						if (firstKey === 'team') {
							pdo_params.columns = 'cm, us, dt';
							swalTitle = 'Solicitar acesso a esta equipe?';
						} else if (firstKey === 'company') {
							pdo_params.columns = 'em, us, dt';
							swalTitle = 'Solicitar acesso a este negócio?';
						}
						swalText = 'Os moderadores receberão a sua solicitação.';
					}

					swal({
						title: swalTitle,
						text: swalText,
						buttons: ['Não', 'Sim'],
						dangerMode: true
					}).then((result) => {
						if (result) {
							goTo('functions/actions.php', 'callback', '', btoa(JSON.stringify(pdo_params)));
							setTimeout(() => {
								if (document.getElementById('callback').innerHTML !== '') {
									location.reload();
								}
							}, 750);
						}
					});
				}
				</script>				
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-user-friends fa-stack-1x fa-inverse dark"></i>					
					</span>				
					<a class="font-weight-500 dark" style="vertical-align: middle;"><?php  echo count($users); ?> Membro<?php if(count($users) > 1){?>s<?php }?></a>
				</div>
				<?php 			
			}elseif(isset($_GET['profile'])){				
				//PROFILE OPTIONS - PRIVACIDADE										
				if(isset($_SESSION['wz']) && $pgid != $_SESSION['wz'] && $pgpc > 0){					
					if($vsg = search('hnw', 'usg', '', "s0 = '{$_SESSION['wz']}' AND s1 = '{$_GET['profile']}'")){
						if(count($vsg) > 0){
						?>
						<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
							<span class="fa-stack" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-eye-slash fa-stack-1x fa-inverse dark"></i>					
							</span>													
							<a onclick="followUser(<?= $_GET['profile'] ?>, 'delete')" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;"> Deixar de Seguir</a>
						</div>
						<?php
						}					
					}else{
					?>
					<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
						<span class="fa-stack" style="vertical-align: middle;">
							<i class="fas fa-circle fa-stack-2x light-gray"></i>
							<i class="fas fa-eye fa-stack-1x fa-inverse dark"></i>					
						</span>													
						<a onclick="followUser(<?= $_GET['profile'] ?>, 'insert')" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;"> Seguir</a>
					</div>											
					<?php 	
					}
					?>
					<script>
					function followUser(profile, action){
						const pdo_params = {
							type: action,
							db: 'hnw',
							table: 'usg'
						};
						if (action == 'delete') {
							pdo_params.where = 's1="' + profile + '" AND s0="<?= $_SESSION['wz'] ?>"';
							swalTitle = 'Deixar de seguir?';
							swalText = 'Você não verá mais as publicações deste usuário na sua tela inicial.';
						} else if (action == 'insert') {
							pdo_params.values = '"<?= $_SESSION['wz'] ?>","' + profile + '"';
							pdo_params.columns = 's0,s1';
							swalTitle = 'Seguir este usuário?';
							swalText = 'Você verá as publicações deste usuário em sua tela inicial.';
						}			
						swal({								
							title: swalTitle,
							text: swalText,
							buttons: ['Não', 'Sim'],
							dangerMode: true
						}).then((result) => {
							if(result){										
								goTo('functions/actions.php', 'callback', '', btoa(JSON.stringify(pdo_params)));																			
								setTimeout(() => {
									if(document.getElementById('callback').innerHTML !== ''){
										location.reload();
									} 
								}, 750);
							}
						});							
					}
					</script>
					<?php
				}elseif(isset($_SESSION['wz']) && $pgid == $_SESSION['wz']){
				?>				
				<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
					<span class="fa-stack" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-cog fa-stack-1x fa-inverse dark"></i>					
					</span>
					<label id="pageConfig" title="Ajustes" onclick="pageConfig()" class="pointer">
						<a class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;"> Ajustes</a>									
					</label>
				</div>
				<script>				
					function pageConfig(){
						toggleSidebar(); 
						var config = $('<div id=config class=height-100></div>'); 
						$('#sidebar').append(config); 
						goTo('partes/resources/modal_content/config_home.php', 'config', '<?= $pgid.'&op=0' ?>', '<?= key($_GET) ?>');
					}											
				</script>
				<?php 
				}						
			}
		}elseif(isset($_SESSION['wz'])){
			//MAIN PAGE
			$username = $loggedUser['un'];
			?>
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-address-card fa-stack-1x fa-inverse dark"></i>					
				</span>				
				<a <?php if(!empty($username)){?> href="/<?php  echo $username; ?>" <?php }else{?> target="_blank" href="/?profile=<?php  echo $_SESSION['wz']; ?>" <?php }?>" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Meu Perfil</a>
			</div>
		<?php 
		}
		
			if($pgpc > 0 && !empty($_GET)){
			?>
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-share fa-stack-1x fa-inverse dark"></i>					
				</span>
				<label title="Ajustes" onclick="shareContent()" class="pointer">
					<a class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;"> Compartilhar</a>									
				</label>
			</div>
			<?php 
			}
			?>
			<hr>
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-user-friends fa-stack-1x fa-inverse dark"></i>					
				</span>				
				<a href="/?users" target="_blank" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Pessoas</a>
			</div>			
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-briefcase fa-stack-1x fa-inverse dark"></i>					
				</span>				
				<a href="/?companies" target="_blank" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Negócios</a>
			</div>
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-users fa-stack-1x fa-inverse dark"></i>					
				</span>				
				<a href="/?teams" target="_blank" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Equipes</a>
			</div>
			<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-pad-5-t cm-pad-5-b">
				<span class="fa-stack" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-sign-out-alt fa-stack-1x fa-inverse dark"></i>	
				</span>				
				<a href="/logout.php" class="font-weight-500 w-color-bl-to-or pointer" style="vertical-align: middle;">Sair</a>
			</div>
			<?php 
		
		?>				
	</div>
</div>