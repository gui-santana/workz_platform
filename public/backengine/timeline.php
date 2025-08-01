<?php
require_once '../includes/Mobile-Detect-2.8.41/Mobile_Detect.php';
require_once '../functions/search.php';

if (!empty($_GET['vr'])) {
    $vr = explode(',', $_GET['vr']);
    if (!empty($vr[0]) && isset($vr[1])) {
        $_GET[$vr[0]] = $vr[1];
    }
}

$detect = new Mobile_Detect();
$mobile = $detect->isMobile() ? 1 : 0;

session_start();
date_default_timezone_set('America/Fortaleza');
setlocale(LC_ALL, 'pt_BR.UTF8');
mb_internal_encoding('UTF8'); 
mb_regex_encoding('UTF8');

// Variáveis de paginação
$uview = isset($_GET['qt']) ? $_GET['qt'] : 0;
$wlimit = (4 * $uview);

// Inicializando as condições de busca
$search_post = '';

// Verificar se estamos em uma página específica (perfil, time ou empresa)
if (isset($_GET['profile'])) {
    // Página de perfil
    $profileId = intval($_GET['profile']);
    $search_post = "us = '{$profileId}' AND cm = '' AND em = '' AND st = '1' ORDER BY dt DESC LIMIT {$wlimit}, 4";
} elseif (isset($_GET['team'])) {
    // Página de time
    $teamId = intval($_GET['team']);
    $search_post = "cm = '{$teamId}' AND st = '1' ORDER BY dt DESC LIMIT {$wlimit}, 4";
} elseif (isset($_GET['company'])) {
    // Página de empresa
    $companyId = intval($_GET['company']);
    $search_post = "em = '{$companyId}' AND st = '1' ORDER BY dt DESC LIMIT {$wlimit}, 4";
} else {
    // Linha do tempo padrão (home)
    if (isset($_SESSION['wz'])) {
        $userId = $_SESSION['wz'];

        // Obter IDs de usuários seguidos
        $people = array_column(search('hnw', 'usg', 's1', "s0 = '{$userId}'"), 's1');
        array_push($people, $userId);

        // Obter IDs de empresas associadas
        $companies = array_unique(array_column(search('cmp', 'employees', 'em', "us = '{$userId}' AND st > 0 AND nv > 0"), 'em'));
        $blockedCompanies = array_unique(array_column(search('cmp', 'companies', 'id', "id IN (" . implode(',', $companies) . ") AND pg = 0"), 'id'));
        $companies = array_diff($companies, $blockedCompanies);

        // Obter IDs de equipes associadas
        $teams = search('cmp', 'teams_users', 'cm,st', "us = '{$userId}'");
        foreach ($teams as $key => $team) {
            $teamDetails = search('cmp', 'teams', 'pg,em', "id = '{$team['cm']}'")[0];
            if ($team['cm'] == 0 || $teamDetails['pg'] == 0 || !in_array($teamDetails['em'], $companies) || $team['st'] == 0) {
                unset($teams[$key]);
            }
        }
        $teams = array_values(array_unique(array_column($teams, 'cm')));
    } else {
        // Visitantes (apenas público)
        $people = array_column(search('hnw', 'hus', 'id', "pg = '2' AND pc = '3'"), 'id');
        $companies = array_column(search('cmp', 'companies', 'id', "pg = '2' AND pc = '3'"), 'id');
        $teams = []; // Remover equipes para visitantes
    }

    // Construir condições de busca para postagens
    $conditions = [];

    if (!empty($people)) {
        $conditions[] = "us IN (" . implode(',', $people) . ") AND st = '1' AND cm = '0' AND em = '0'";
    }

    if (isset($_SESSION['wz']) && !empty($teams)) {
        $conditions[] = "cm IN (" . implode(',', $teams) . ") AND st = '1'";
    }

    if (!empty($companies)) {
        $conditions[] = "em IN (" . implode(',', $companies) . ") AND st = '1' AND tp <> 9";
    }
	
	if (!isset($_SESSION['wz'])) {
		if (!empty($people)) {
			$conditions[] = "us IN (" . implode(',', $people) . ") AND st = '1' AND cm = '0' AND em = '0'";
		}

		if (!empty($companies)) {
			$conditions[] = "us IN (" . implode(',', $people) . ") AND em IN (" . implode(',', $companies) . ") AND st = '1'";
		}
	}

	// Constrói a query somente se houver condições
	if (!empty($conditions)) {		
		$search_post = implode(' OR ', $conditions) . " ORDER BY dt DESC LIMIT {$wlimit}, 4";
	} else {
		$search_post = "st = '0'"; // Para garantir que nenhuma postagem seja retornada se não houver condições
	}
}

// Função para gerar o conteúdo do dropdown
function generateDeleteDropdown($id) {
	ob_start();
	?>
	<div class="dropdown position-absolute abs-r-20 abs-t-20 display-inline" style="float:right;">
		<div class="dropbtn pointer cm-pad-10-b cm-pad-10-l">
			<i class="fas fa-ellipsis-v"></i>
		</div>
		<div class="dropdown-content position-absolute abs-r-0 background-gray w-shadow-1 z-index-1 w-rounded-10 abs-r-0">
			<a class="w-color-bl-to-or cm-pad-10 display-block pointer"
			   onclick="postDelete(<?= $id ?>)">			  
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-trash-alt fa-stack-1x fa-inverse dark"></i>
				</span>
				Excluir
			</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

// Buscar postagens
$posts = search('hnw', 'hpl', '', $search_post);
$count_posts = count($posts);

// Função alternativa para verificar se uma string termina com outra
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, -$length) === $needle);
}

// Exibir postagens
if ($count_posts > 0) {
    foreach ($posts as $post) {
        $author = search('hnw', 'hus', 'tt,un,im', "id = {$post['us']}")[0];
        ?>
        <div class="large-6 medium-6 small-12 float-left cm-pad-10-h cm-mg-20-b">
            
			<!-- Publicação -->
			<div class="tab large-12 medium-12 small-12 position-relative proportional-4-3">
				<div class="position-absolute height-100 large-12 medium-12 small-12 abs-t-0 abs-l-0 w-rounded-20 w-shadow-2">				
				
					<!-- Conteúdo da Publicação -->
					<div class="position-absolute height-100 large-12 medium-12 small-12 abs-t-0 abs-r-0 w-rounded-20 overflow-hidden">						
					<?php 						
					//PUBLICAÇÃO DO ASSISTENTE
					if ($post['tp'] == 0){					
						echo str_replace('contenteditable="true"', '', bzdecompress(base64_decode($post['ct'])));							
						
					}else{						
						$data = json_decode(bzdecompress(base64_decode($post['ct'])), true);
						
						//HISTÓRIA
						if($post['tp'] == 9){
							if ($data['type'] == 'video') {
								$videoPath = htmlspecialchars($data['path']);
								?>	
								<iframe id="webPlayer" class="video-iframe w-rounded-20-b large-12 medium-12 small-12 height-100 border-none" src="<? echo htmlspecialchars($data['path']); ?>&muted=1&loop=1&controls=0" title="Workz! TV Player" frameborder="0" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" ></iframe>
								<?php
							}
							
						//VÍDEO INCORPORADO
						}elseif($post['tp'] == 8){
							if ($data['type'] == 'embed_video') {
								$videoPath = htmlspecialchars($data['path']);
								?>	
								<iframe id="webPlayer" class="video-iframe w-rounded-20-b large-12 medium-12 small-12 height-100 border-none" src="<? echo htmlspecialchars($data['path']); ?>" frameborder="0" allow="fullscreen; picture-in-picture" ></iframe>
								<?php
							}
							
						//LINK DE NOTÍCIA
						}elseif($post['tp'] == 7){
							if ($data['type'] == 'embed_link') {
								// Verifica se houve erro
								if ($data === null) {
									echo "Erro ao decodificar ou descompactar a string.";
								} else {
									$imageUrl = $data['image'] ?: '/images/no-image.jpg';
									?>							
									<img class="position-absolute large-12 medium-12 small-12 height-100 w-rounded-20" src="<?php echo $imageUrl; ?>" style="object-fit: cover; object-position: center;" />									
									<?php
								}
							}
							
						//FOTO
						}elseif($post['tp'] == 6){							
						
						//PÁGINA
						}elseif($post['tp'] == 1){
							$data['type'] = 'embed_link';
							$data['path'] = '?post='.$post['id'];
							$data['site_name'] = '';
							$data['title'] = $post['tt'];
							
							$imageUrl = base64_decode($post['im']) ?: '/images/no-image.jpg';
							?>						
							<img class="position-absolute large-12 medium-12 small-12 height-100 w-rounded-20" src="<?php echo $imageUrl; ?>" style="object-fit: cover; object-position: center;"></img>						
							<?php											
						}				
						?>
						<div class="w-rounded-20-b position-absolute abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12" style="background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,.5) 100%);">
							<div class="cm-pad-15-h cm-pad-50-t">
								<?php
								if ($data['type'] == 'embed_link') {									
								?>
								<a class="w-color-wh-to-or" href="<?php echo $data['path']; ?>" target="_blank">
									<small class="text-ellipsis white"><?php echo htmlspecialchars($data['site_name']); ?></small>
									<h3 class="font-weight-500 text-ellipsis-2 "><?php echo htmlspecialchars($data['title']); ?></h3>
								</a>
								<?php
								}
								if(!empty($post['lg'])){
								?>								
								<div onclick="toggleLg(this)" class="text-ellipsis-2 white cm-mg-10-t"><?php echo base64_decode($post['lg']); ?></div>
								<?php								
								}
								?>
							</div>
							<div id="dynamic_bottom_post_<?php echo $post['id']; ?>" class="large-12 medium-12 small-12 display-center-general-container cm-pad-15 cm-pad-10-t" style="min-height: 60px;">
								<!-- Comentários -->
								<div class="text-left text-ellipsis text-right white fs-c large-9 medium-9 small-9">
									<?php
									// Busca o primeiro comentário para exibição
									$comments = search('hnw', 'hpl_comments', 'pl,us,ds', "pl = '" . $post['id'] . "'");
									if (count($comments) > 0) {
										$commentator = search('hnw', 'hus', 'un,tt', "id = {$comments[0]['us']}")[0];
										$commentatorLink = $commentator['un'] ? "/{$commentator['un']}" : "?profile={$comments[0]['us']}";
										$commentatorName = $commentator['un'] ?: strtok($commentator['tt'], ' ');
										?>
										<div class="large-12 medium-12 small-12 text-ellipsis text-left">
											<a target="_blank" class="w-color-wh-to-or pointer font-weight-600" href="<?php echo $commentatorLink; ?>">
												<?php echo $commentatorName; ?>
											</a>
											<span><?php echo $comments[0]['ds']; ?></span>
										</div>
										<div class="comment large-12 medium-12 small-12 text-ellipsis text-left white">
											<a class="pointer w-color-wh-to-or font-weight-600" onclick="postComments(<?= $post['id'] ?>)">
												Ver todos os <?php echo count($comments); ?> comentários
											</a>
										</div>
									<?php } ?>
								</div>								
								<!-- Curtidas e Comentários -->
								<div id="dynamic_bottom_like_<?php echo $post['id']; ?>" class="text-right large-3 medium-3 small-3">
									<?php					
									include('../backengine/like.php')
									?>									
								</div>

								<div class="clear"></div>
							</div>
						</div>
						<!-- Parte Inferior da Publicação -->						
						<?php						
					}
					?>					
					</div>
					
					<!-- Parte Superior da Publicação -->
					<div class="position-relative white cm-pad-15 w-rounded-20-t" style="height: 100px; background: linear-gradient(to top, rgba(0,0,0,0) 0%, rgba(0,0,0,0.3) 100%);">
						<div class="w-circle cm-pad-20 w-shadow-1 pointer float-left"
							 style="height: 35px; width: 35px; background: url(data:image/jpeg;base64,<?php echo $author['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">
						</div>
						<div class="cm-pad-5-t cm-pad-5-b cm-pad-10-h float-left" style="width: calc(100% - 50px);">
							<div class="large-12 medium-12 small-12">
								<div class="fs-c font-weight-600 text-ellipsis" style="margin-top: -2.5px">
									<a class="w-color-wh-to-or" href="/<?php echo $author['un'] ?: '?profile=' . $post['us']; ?>" target="_blank">
										<?php echo $author['tt']; ?>
									</a>
									<?php
									if ($post['em'] > 0 || $post['cm'] > 0) {
										$table = $post['em'] > 0 ? 'companies' : 'teams';
										$column = $post['em'] > 0 ? 'em' : 'cm';
										$urlParam = $post['em'] > 0 ? 'company' : 'team';

										$res = search('cmp', $table, 'tt,un', "id = {$post[$column]}")[0];
										$link = $res['un'] ? $res['un'] : "?{$urlParam}={$post[$column]}";
										?>
										<a class="cm-mg-5-h"><i class="fas fa-caret-right"></i></a>
										<a class="w-color-wh-to-or" target="_blank" href="/<?php echo $link; ?>">
											<?php echo $res['tt']; ?>
										</a>
										<?php
									}
									?>
								</div>
								<p class="fs-b text-ellipsis"><?php echo ucfirst(strftime('%A, %e de %B de %Y, às %H:%M', strtotime($post['dt']))); ?></p>								
							</div>							
						</div>
						<?php
						// Verifica se o usuário está logado e é o autor do post
						if (isset($_SESSION['wz']) && $post['us'] == $_SESSION['wz']){													
							// Exibe o dropdown
							echo generateDeleteDropdown($post['id']);
						}
						?>
					</div>
					
				</div>			
			</div>				
		</div>			
    <?php
    }
	$search_post_limitless = preg_replace('/ORDER BY.+$/', '', $search_post);
	$has_more = count(search('hnw', 'hpl', 'id', "$search_post_limitless ORDER BY dt DESC LIMIT " . ($wlimit + 4) . ", 1")) > 0;
    // Adicionar um marcador para carregar mais postagens
    if (!$has_more) {
		echo '<div class="no-more-content" data-end="1"></div>';
	}
}
?>
