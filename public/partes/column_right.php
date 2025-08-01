<div class="cm-pad-15-h position-relative">	
		<?		
		if(isset($_SESSION['wz'])){				
			//USERS				
			if(count($_GET) == 0){
				$users = array_column(search('hnw', 'usg', 's1', "s0 = '".$_SESSION['wz']."'"), 's1');
			}elseif(isset($_GET['profile'])){
				$users = $followed;
			}
			foreach($users as $key => $user){
				if($user == 0 || empty(search('hnw', 'hus', 'id', "id = '".$user."'"))){
					unset($users[$key]);
				}
			}
			$users = array_values($users);
			
			?>
			
			<div class="w-rounded-20 background-white cm-pad-7-5 w-shadow-1 cm-mg-30-b">
				<div class="large-12 medium-12 small-12 display-center-general-container">
					<div class="large-6 medium-6 small-6 cm-pad-7-5 cm-pad-5-h text-ellipsis">
					<span class="fa-stack orange">
						<i class="fas fa-circle fa-stack-2x"></i>
						<i class="fas fa-user-friends fa-stack-1x fa-inverse"></i>					
					</span>					
					<a class="font-weight-500" style="vertical-align: middle;"> <?if(isset($_GET['company']) || isset($_GET['team'])){?>Membros<?}else{?>Seguindo<?} if(count($users) > 0){ echo ' ('.count($users).')'; }?></a>
					</div>
					<?
					if(count($users) > 6){
					?>
					<div class="large-6 medium-6 small-6 text-right cm-pad-7-5-r">
						<a title="Ver todos" target="_blank" href="/?<?if(!empty($_GET['profile'])){?>users&u=<?echo $_GET['profile'];}elseif(!empty($_GET['team'])){?>users&t=<?echo $_GET['team'];}elseif(!empty($_GET['company'])){?>users&c=<?echo $_GET['company'];}else{?>users&u=<?echo $_SESSION['wz'];}?>" class="w-color-or-to-bl">Ver todos <span>›</span></a>
					</div>
					<?
					}
					?>
				</div>
				<div class="clearfix">
					<?						
					if(count($users) > 0){
						for($i = 0; $i < 6; $i++){
							if(array_key_exists($i, $users)){									
								if($user = search('hnw', 'hus', 'ps,im,tt,un,pc', "id = '".$users[$i]."'")[0]){
									if(count($user) > 0){
									?>
									<a <? if($user['pc'] > 0){?> title="Ver Perfil" <?if(!empty($user['un'])){?>  target="_blank" href="/<? echo $user['un']; ?>" <?}else{?> href="/?profile=<? echo $users[$i]; ?>" <?} }?>>
									<div class="large-4 medium-4 small-4 cm-pad-7-5 float-left w-color-bl-to-or">								
										<div class="large-12 w-square position-relative">
											<div class="w-rounded-15 w-square-content <? if($user['pc'] > 0){?> pointer <?}else{?> bnw <?}?> w-shadow-1 position-relative" style="background: url(data:image/jpeg;base64,<? echo $user['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />																						
												<div class="w-rounded-15 large-12 medium-12 small-12 background-black-transparent-25 height-100 abs-b-0 text-center cm-pad-10-b cm-pad-10-t font-weight-500 w-color-bl-to-or w-shadow-1-t position-absolute">
													<p class="white position-absolute large-12 medium-12 small-12 abs-b-5 abs-l-0 text-ellipsis cm-pad-10-h text-shadow"><? echo $user['tt']; ?></p>
												</div>
											</div>
										</div>								
									</div>																																
									</a>
									<?
									}	
								}								
							}
						}
					}else{
					?>						
					<div class="large-12 cm-pad-5">						
						<div class="w-rounded-30 large-12 medium-12 small-12 cm-pad-7-5 text-ellipsis" style="background: #F7F8D1;">
							<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma página de usuário.
						</div>
					</div>
					<?	
					}
					?>												
				</div>					
			</div>					
			<?
			//TEAMS
			if(count($_GET) == 0){
				//HOME
				/*
				//GET LOGGED USER'S ACTIVE COMPANIES
				$companies = array_unique(array_column(search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."'"), 'em'));
				foreach($companies as $key => $company){
					$empg = search('cmp', 'companies', 'pg', "id = '".$company."'")[0]['pg'];
					if($empg == 0){
						unset($companies[$key]);
					}
				}
				//GET LOGGED USER'S TEAMS
				$teams = search('cmp', 'teams_users', 'cm,st', "us = '".$_SESSION['wz']."'");				
				foreach($teams as $key => $team){					
					$cm = $team['cm'];
					$st = $team['st'];
					$team = search('cmp', 'teams', 'pg,em', "id = '".$cm."'");
					if($cm == 0 || $team[0]['pg'] == 0 || !in_array($team[0]['em'], $companies) || $st == 0){
						unset($teams[$key]);
					}
				}
				$teams = array_values(array_unique(array_column($teams, 'cm')));				
				*/
			}
			//LOCK OR UNLOCK THE VIEW OF THIS BLOCK
			$teams_lock = '0';
			if(isset($_GET['team']) && !in_array($_SESSION['wz'], $moderators)){
				$teams_lock = '1';
			}
			if($teams_lock == '0'){
			?>				
							
			<div class="w-rounded-20 background-white cm-pad-7-5 w-shadow-1 cm-mg-30-b clearfix">

			<div class="large-12 medium-12 small-12 display-center-general-container">
				<div class="large-6 medium-6 small-6 cm-pad-7-5 cm-pad-5-h text-ellipsis">
				<span class="fa-stack orange">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-users fa-stack-1x fa-inverse"></i>					
				</span>					
				<a class="font-weight-500" style="vertical-align: middle;"> <?if(isset($_GET['team'])){?>Solicitações <?if(count($requests) > 0){echo'('.count($requests).')';} }else{?>Equipes <?if(!isset($_GET) && count($_SESSION['teams']) > 0){echo'('.count($_SESSION['teams']).')';}elseif(count($teams) > 0){ echo '('.count($teams).')'; }}?>  </a>
				</div>
				<?
				if(!isset($_GET['team']) && count($teams) > 6){
				?>
				<div class="large-6 medium-6 small-6 text-right">
					<a title="Ver todas" target="_blank" href="/?<?if(!isset($_GET)){?>teams&user=<?echo $_SESSION['wz'];}else{if(!empty($_GET['profile'])){?>teams&user=<?echo $_GET['team'];}elseif(!empty($_GET['team'])){?>teams&team=<?echo $_GET['team'];}elseif(!empty($_GET['company'])){?>teams&company=<?echo $_GET['team'];}}?>" class="w-color-or-to-bl">Ver todas <span>›</span></a>
				</div>
				<?
				}
				?>
			</div>
			<?
			//LISTA DE EQUIPES DO USUÁRIO
			if(!isset($_GET['team'])){
				if(count($teams) == 0){
				?>
				<div class="large-12 cm-pad-5">						
					<div class="w-rounded-30 large-12 medium-12 small-12 cm-pad-10 text-ellipsis" style="background: #F7F8D1;">
						<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma página de equipe.
					</div>
				</div>
				<?
				}else{					
					for($i = 0; $i < 6; $i++){
						if(array_key_exists($i, $teams)){
						$team = search('cmp', 'teams', 'tt,im', "id = '".$teams[$i]."'")[0];									
						?>
						<div class="large-4 medium-4 small-4 cm-pad-7-5 float-left w-color-bl-to-or">
						<a target="_blank" href="/?team=<? echo $teams[$i]; ?>">
							<div class="large-12 w-square position-relative">											
								<div class="w-rounded-15 w-square-content w-shadow-1 pointer position-relative" style="background: url(data:image/jpeg;base64,<? echo $team['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />
									<div class="w-rounded-15 large-12 medium-12 small-12 background-black-transparent-25 height-100 abs-b-0 text-center cm-pad-10-b cm-pad-10-t font-weight-500 w-color-bl-to-or w-shadow-1-t position-absolute">
										<p class="white position-absolute large-12 medium-12 small-12 abs-b-5 abs-l-0 text-ellipsis cm-pad-10-h"><? echo $team['tt']; ?></p>
									</div>																						
								</div>																					
							</div>										
						</a>
						</div>																																
						<?
						}
					}
				}
				?>							
			<?												
			}else{
			//SOLICITAÇÕES DE ACESSO À EQUIPE
				?>
				<div class="large-12">																			
					<?
					if(count($requests) > 0){
					?>
					<div class="cm-pad-10 large-12 medium-12 small-12 w-rounded-20 js-flickity" data-flickity-options='{ "cellAlign": "left", "imagesLoaded": true, "percentPosition": false, "prevNextButtons": false, "pageDots": false, "fullscreen": false }'>
					<?
					foreach($requests as $request){
						if($user = search('hnw', 'hus', 'id,im,ps,tt,un,pc', "id = '".$request."'")[0]){							
						?>
						<div class="w-rounded-15 cm-pad-15 <?if(count($requests) > 1){?>large-11 medium-11 small-11 cm-mg-5-h<?}else{?>large-12 medium-12 small-12<?}?> position-relative background-gray" style="display: inline-block;">
							<div class="large-12 medium-12 small-12 text-ellipsis">
								<a title="Ver perfil" target="_blank" href="?profile=<? echo $user['id']; ?>"><img class="w-circle cm-mg-5-r" style="height: 35px; width: 35px; vertical-align: middle;" src="data:image/png;base64,<? echo $user['im']; ?>" ></img></a>
								<a title="Ver perfil" target="_blank" class="font-weight-500 w-color-bl-to-or" style="vertical-align: middle;" href="?profile=<? echo $user['id']; ?>"><? echo $user['tt']; ?></a>										
							</div>
							<hr>
							<div class="text-center">
								<div class="float-left large-6 medium-6 small-6">
									<?
									$pdo_params = array(
										'type' => 'update',
										'id' => $user['id'],
										'db' => 'cmp',
										'table' => 'teams_users',
										'where' => 'us="'.$user['id'].'" AND cm="'.$_GET['team'].'"',
										'values' => 'st="1"',
										'columns' => ''
									);										
									$vr = base64_encode(json_encode($pdo_params));
									?>
									<div onclick="
											swal({
												title: 'Tem certeza?',
												text: 'Aceita <? echo $user['tt']; ?> como membro desta equipe?',
												icon: 'warning',
												buttons: true,
												dangerMode: true
											}).then((result) => {
												if(result){													
													goTo('../functions/actions.php', 'callback', '', '<? echo $vr; ?>');
													setTimeout(() => {
														if(document.getElementById('callback').innerHTML !== ''){
															location.reload();
														} 
													}, 500);
												}
											});"
										class="cm-pad-10 w-rounded-30-l w-color-bl-to-or pointer font-weight-500 large-12 medium-12 small-12 w-bkg-wh-to-gr">										
										<i class="fas fa-user-plus orange"></i>
										Aceitar
									</div>
								</div>
								<div class="float-left large-6 medium-6 small-6 clearfix">
									<?
									$pdo_params = array(
										'type' => 'delete',											
										'db' => 'cmp',
										'table' => 'teams_users',
										'where' => 'us="'.$user['id'].'" AND cm="'.$_GET['team'].'"'
									);
									$vr = base64_encode(json_encode($pdo_params));
									?>
									<div onclick="
											swal({
												title: 'Tem certeza?',
												text: 'Deseja excluir permanentemente a solicitação de <? echo $user['tt']; ?>?',
												icon: 'warning',
												buttons: true,
												dangerMode: true
											}).then((result) => {
												if(result){													
													goTo('../functions/actions.php', 'callback', '', '<? echo $vr; ?>');
													setTimeout(() => {
														if(document.getElementById('callback').innerHTML !== ''){
															location.reload();
														} 
													}, 500);
												}
											});"
										class="cm-pad-10 w-rounded-30-r w-color-bl-to-or pointer font-weight-500 large-12 medium-12 small-12 w-bkg-wh-to-gr">
										<i class="fas fa-user-times orange"></i>
										Recusar
									</div>								
								</div>
							</div>
						</div>	
						<?php
						}				
					}
					?>							
					</div>
					<?
					}else{
					?>									
					<div class="large-12 cm-pad-7-5">						
						<div class="w-rounded-30 large-12 medium-12 small-12 cm-pad-10 text-ellipsis" style="background: #F7F8D1;">
							<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma solicitação de acesso.
						</div>
					</div>
					<?
					}																												
					?>									
				</div>
				<?
			}
			?>
			</div>									
			<?	
			}
			//COMPANIES			
			//LOCK OR UNLOCK THE VIEW OF THIS BLOCK				
			$companies_lock = '0';
			if((isset($_GET['company']) && !in_array($_SESSION['wz'], $moderators)) || isset($_GET['team'])){
				$companies_lock = '1';
			}
			if($companies_lock == '0'){
			?>							
			
			<div class="w-rounded-20 background-white cm-pad-7-5 w-shadow-1 cm-mg-30-b clearfix">					
				<div class="large-12 medium-12 small-12 display-center-general-container">
					<div class="large-6 medium-6 small-6 cm-pad-7-5 cm-pad-5-h text-ellipsis">
					<span class="fa-stack orange">
						<i class="fas fa-circle fa-stack-2x"></i>
						<i class="fas fa-briefcase fa-stack-1x fa-inverse"></i>					
					</span>				
					<a class="font-weight-500" style="vertical-align: middle;"> <?if(isset($_GET['company'])){?>Solicitações <?if(count($requests) > 0){echo'('.count($requests).')';} }else{?>Negócios <?if(!isset($_GET) && count($_SESSION['companies']) > 0){echo'('.count($_SESSION['companies']).')';}elseif(count($companies) > 0){ echo '('.count($companies).')'; }}?>  </a>
					</div>
					<?
					if(!isset($_GET['company']) && count($companies) > 6){					
					?>
					<div class="large-6 medium-6 small-6 text-right">
						<a title="Ver todos" target="_blank" href="/?<?if(!isset($_GET)){?>companies&user=<?echo $_SESSION['wz'];}else{if(!empty($_GET['profile'])){?>companies&user=<?echo $_GET['team'];}elseif(!empty($_GET['company'])){?>companies&company=<?echo $_GET['team'];}}?>" class="w-color-or-to-bl">Ver todos <span>›</span></a>
					</div>
					<?
					}
					?>
				</div>
				<?
				//MAIN PAGE
				if(!isset($_GET['company'])){
					if(count($companies) == 0){
					?>
					<div class="large-12 cm-pad-7-5">						
						<div class="w-rounded-30 large-12 medium-12 small-12 cm-pad-10 text-ellipsis" style="background: #F7F8D1;">
							<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma página de negócio.
						</div>
					</div>
					<?
					}else{						
						for($i = 0; $i < 6; $i++){							
							if(array_key_exists($i, $companies)){								
							$company = search('cmp', 'companies', 'tt,im', "id = '".$companies[$i]."'")[0];								
							?>
							<div class="large-4 medium-4 small-4 cm-pad-7-5 float-left w-color-bl-to-or">
							<a target="_blank" href="/?company=<? echo $companies[$i]; ?>">
								<div class="large-12 w-square position-relative">											
									<div class="w-rounded-15 w-square-content w-shadow-1 pointer position-relative" style="background: url(data:image/jpeg;base64,<? echo $company['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />
										<div class="w-rounded-15 large-12 medium-12 small-12 background-black-transparent-25 height-100 abs-b-0 text-center cm-pad-10-b cm-pad-10-t font-weight-500 w-color-bl-to-or w-shadow-1-t position-absolute">
											<p class="white position-absolute large-12 medium-12 small-12 abs-b-5 abs-l-0 text-ellipsis cm-pad-10-h"><? echo $company['tt']; ?></p>
										</div>																						
									</div>																					
								</div>										
							</a>
							</div>																																
							<?
							}
						}
					}				
				//COMPANY'S PAGE
				}else{
					if(count($requests) > 0){
						?>
						<div class="cm-pad-10 large-12 medium-12 small-12 w-rounded-20 js-flickity" data-flickity-options='{ "cellAlign": "left", "imagesLoaded": true, "percentPosition": false, "prevNextButtons": false, "pageDots": false, "fullscreen": false }'>
						<?
						foreach($requests as $request){
						$user = search('hnw', 'hus', 'id,ps,im,tt,un,pc', "id = '".$request."'")[0];							
						?>
						<div class="w-rounded-15 cm-pad-15 <?if(count($requests) > 1){?>large-11 medium-11 small-11 cm-mg-5-h<?}else{?>large-12 medium-12 small-12<?}?> position-relative background-gray" style="display: inline-block;">
							<div class="large-12 medium-12 small-12 text-ellipsis">
								<img class="w-circle cm-mg-5-r" style="height: 35px; width: 35px; vertical-align: middle; object-fit: cover; object-position: center;" src="data:image/png;base64,<? echo $user['im']; ?>" ></img>
								<a target="_blank" title="Ver perfil" class="font-weight-500 w-color-bl-to-or" style="vertical-align: middle;" href="?profile=<? echo $user['id']; ?>"><? echo $user['tt']; ?></a>										
							</div>
							<hr>
							<div class="text-center clearfix">
								<div class="float-left large-6 medium-6 small-6">
									<?
									$pdo_params = array(
										'type' => 'update',
										'id' => $user['id'],
										'db' => 'cmp',
										'table' => 'employees',
										'where' => 'us="'.$user['id'].'" AND em="'.$_GET['company'].'"',
										'values' => 'st="1"',
										'columns' => ''
									);										
									$vr = base64_encode(json_encode($pdo_params));
									?>
									<div onclick="
										goTo('functions/actions.php', 'callback', '', '<? echo $vr; ?>');
										setTimeout(() => {
											if(document.getElementById('callback').innerHTML !== ''){
												location.reload();
											} 
										}, 500);
										" class="cm-pad-10 w-rounded-30-l w-color-bl-to-or pointer font-weight-500 large-12 medium-12 small-12 w-bkg-wh-to-gr">										
										<i class="fas fa-user-plus orange"></i>
										Aceitar
									</div>
								</div>
								<div class="float-left large-6 medium-6 small-6">
									<?
									$pdo_params = array(
										'type' => 'delete',											
										'db' => 'cmp',
										'table' => 'employees',
										'where' => 'us="'.$user['id'].'" AND em="'.$_GET['company'].'"'
									);
									$vr = base64_encode(json_encode($pdo_params));
									?>
									<div onclick="
										swal({
											title: 'Tem certeza?',
											text: 'Deseja recusar a solicitação de <? echo $user['tt']; ?>?',
											icon: 'warning',
											buttons: true,
											dangerMode: true
										}).then((result) => {
											if(result){												
												goTo('../functions/actions.php', 'callback', '', '<? echo $vr; ?>');
												setTimeout(() => {
													if(document.getElementById('callback').innerHTML !== ''){
														location.reload();
													} 
												}, 500);
											}
										});					
										" class="cm-pad-10 w-rounded-30-r w-color-bl-to-or pointer font-weight-500 large-12 medium-12 small-12 w-bkg-wh-to-gr">
										<i class="fas fa-user-times orange"></i>
										Recusar
									</div>									
								</div>								
							</div>
						</div>															
						<?
						}
						?>							
						</div>
						<?
					}else{
					?>
					<div class="large-12 cm-pad-5">						
						<div class="w-rounded-30 large-12 medium-12 small-12 cm-pad-10 text-ellipsis" style="background: #F7F8D1;">
							<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma solicitação de acesso.
						</div>
					</div>							
					<?
					}
				}
				?>
			</div>
			<?
			}
		}elseif(!empty($_GET)){
		?>
		<div id="login" class="w-rounded-20 background-white cm-pad-20 w-shadow-1">
		<?php
			include('loginZ.php');
		?>
		</div>
		<?php
		}
		?>
</div>