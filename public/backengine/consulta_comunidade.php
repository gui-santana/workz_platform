
<?php 
session_start();
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

$qt = explode('|', $_GET['qt']);
$search_type = explode(',', $qt[1]);

if(array_key_exists(1, $search_type)){
	//SEARCH INSIDE A PAGE
	$infos = explode(':', $search_type[1]);
	$page_tp = $infos[0];
	$page_id = $infos[1];	
}else{
	//SEARCH OUTSIDE
}

if($qt[0] == '0'){
	$uview = 0;
}else{
	$uview = $qt[0];
}
$wlimit = (18*$uview);

$where = '';

if($search_type[0] == 't'){
	//TEAMS
	$companies = array_unique(array_column(search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."' AND st > 0 AND nv > 0"), 'em'));
	$blocked_companies = array_unique(array_column(search('cmp', 'companies', 'id', "id IN (".implode(',', $companies).") AND pg = 0"), 'id'));
	$companies = array_diff($companies, $blocked_companies);	
	if(!empty($_GET['vr'])){
		if(isset($_SESSION['wz'])){			
			foreach($companies as $companies_teams){
				$where .= "em = '".$companies_teams."' AND pg > '0' AND tt LIKE '%".$_GET['vr']."%' OR ";
			}
			$where .= "em = '0' AND pg > '0' AND tt LIKE '%".$_GET['vr']."%' OR ";
		}
		$where .= "em = '0' AND pg = '2' AND tt LIKE '%".$_GET['vr']."%' ORDER BY tt ASC LIMIT ".$wlimit.",18 ";
	}else{
		if(isset($_SESSION['wz'])){
			foreach($companies as $companies_teams){
				$where .= "em = '".$companies_teams."' AND pg > '0' OR ";
			}
			$where .= "em = '0' AND pg > '0' OR ";
		}
		$where .= "em = '0' AND pg = '2' ORDER BY tt ASC LIMIT ".$wlimit.",18 ";
	}	
	$results = search('cmp', 'teams', '', $where);
	$pagename = 'equipe';
}elseif($search_type[0] == 'c'){
	//COMPANIES
	if(!empty($_GET['vr'])){
		if(isset($_SESSION['wz'])){
			$where .= "pg = '1' AND tt LIKE '%".$_GET['vr']."%' OR ";
		}
		$where .= "pg = '2' AND tt LIKE '%".$_GET['vr']."%' ORDER BY tt ASC LIMIT ".$wlimit.",18 ";
	}else{
		if(isset($_SESSION['wz'])){
			$where .= "pg = '1' OR ";
		}
		$where .= "pg = '2' ORDER BY tt ASC LIMIT ".$wlimit.",18 ";
	}
	$results = search('cmp', 'companies', '', $where);	
	$pagename = 'negócio';
}elseif($search_type[0] == 'p'){
	//PROFILES
	if(!empty($_GET['vr'])){
		if(isset($_SESSION['wz'])){
			$where .= "pg = '1' AND tt LIKE '%".$_GET['vr']."%' OR ";
		}
		$where .= "pg = '2' AND tt LIKE '%".$_GET['vr']."%' ORDER BY tt ASC LIMIT ".$wlimit.",18 ";
	}else{
		if(isset($_SESSION['wz'])){
			$where .= "pg > '0' OR";
		}
		$where .= "DER BY tt ASC";
	}
	$results = search('hnw', 'hus', '', $where);
	$pagename = 'usuário';
}

if(count($results) == 0){
	?>
	<div class="large-12 medium-12 small-12">
		<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
			<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma página de <?php  echo $pagename; ?> foi encontrada.
		</div>
	</div>	
	<?php 
}else{
	?>
	<div id="search" class="row">
		<div class="large-12">	
		<?php 		
		$fakeImg = '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';
		foreach($results as $result){
			if($search_type[0] == 't'){
				$wtt = $result['tt'];
				$url = 'https://workz.com.br?team='.$result['id'];
				$qtd = count(search('cmp', 'teams_users', 'id', "cm = {$result['id']}")).' membros';							
				$img = $result['im'];
				//$dtn = 'Criada em '.utf8_encode(strftime('%d de %B de %Y', strtotime($result['dt'])));
			}elseif($search_type[0] == 'c'){
				$wtt = $result['tt'];
				$url = 'https://workz.com.br?company='.$result['id'];				
				$qtd = count(search('cmp', 'employees', 'id', "em = {$result['id']}")).' membros';
				$img = $result['im'];
				//$dtn = 'Criada em '.utf8_encode(strftime('%d de %B de %Y', strtotime($result['dt'])));
			}elseif($search_type[0] == 'p'){
				$wtt = $result['tt'];
				$url = 'https://workz.com.br?profile='.$result['id'];
				$qtd = count(search('hnw', 'usg', 'id', "s1 = {$result['id']}")).' seguindo';							
				$resultSearch = search('hnw', 'hus', 'im', "id = '{$result['id']}'");
				$img = empty($resultSearch[0]['im']) ? $fakeImg : $resultSearch[0]['im'];				
				//$dtn = 'Aniversário em '.utf8_encode(strftime('%d de %B', strtotime(getByProtSpot($result['ps'], 'birthday'))));
			}
			
			?>		
			<div class="large-4 medium-6 small-12 float-left cm-pad-15-h cm-mg-30-b">
				<a class="w-color-bl-to-or" href="<?php  echo $url; ?>">
				<div class="w-rounded-20 w-comm-list w-shadow large-12 medium-12 small-12 position-relative w-bkg-wh-to-gr display-center-general-container">									
					<div class="large-4 medium-4 small-4 float-left cm-pad-20-r">
						<div class="large-12 w-square position-relative">											
							<div class="w-rounded-20-l w-square-content height-100"  style="background: url(data:image/jpeg;base64,<?php  echo $img; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">						
							</div>					
						</div>
					</div>
					<div class="large-8 medium-8 small-8 float-left position-relative">
						
							<p class="cm-mg-10-b font-weight-500 text-ellipsis"><?php  echo $wtt; ?></p>
							<p class="cm-mg text-ellipsis gray">
							<?php												
							echo $search_type[0] == 't' ? ($result['em'] != 0 ? 'Equipe de ' . search('cmp', 'companies', 'tt', "id = {$result['em']}")[0]['tt'] : '') : $qtd;
							?>
							</p>
					</div>
					<div class="clear"></div>					
				</div>
				</a>
			</div>		
			<?php 
		}
		?>
		</div>		
	</div>
	<?php 
	$uview = $uview + 1;
	if(count($results) == 18){
	?>
	<div id="dynamic_results_<?php  echo $uview; ?>" class="large-12 medium-12 small-12">
	</div>	
	<?php 	
	}
}
?>