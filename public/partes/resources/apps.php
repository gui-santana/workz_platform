<?
if($mobile == 1){
	$nPag = 9;
}else{
	$nPag = 8;
}

//NATIVE APPS (ST = 1)
$native = array_column(search('app', 'apps', 'id', "st = '1'"), 'id');

//BUSCA APPS DE USUÁRIO
$user_apps = array_column(search('app', 'gapp', 'ap', "us = '".$_SESSION['wz']."' AND st = 1"), 'ap');

//BUSCA APPS DE NEGÓCIOS
$companies_apps = array();
foreach($companies as $company){
	$pg = search('cmp', 'companies', 'pg', "id = '".$company."'")[0]['pg'];
	
	$companies_apps[$company] = array_column(search('app', 'gapp', 'ap', "em = '".$company."'"), 'ap');
	
}
$companies_apps = array_merge(...array_values($companies_apps));
array_unique($companies_apps);

$allApps = array_unique(array_merge($native, $user_apps, $companies_apps));

$arr = array();
foreach($allApps as $app){
	//BUSCA O APP
	$result_app = search('app', 'apps', 'id,tt,im,nm,us', "id = '".$app."'")[0];	
	//VERIFICA SE O APP ESTÁ SENDO ATUALIZADO POR UM USUÁRIO
	if($result_app['us'] == 0 || $result_app['us'] == $_SESSION['wz']){
		$result = array(
			'id' => $result_app['id'],
			'tt' => $result_app['tt'],
			'im' => $result_app['im'],
			'nm' => $result_app['nm']
		);
		array_push($arr, $result);
	}
}
$tt = array_column($arr, 'tt');
array_multisort($tt, SORT_ASC, $arr);

$arrQtd = count($arr);
$np = ceil($arrQtd/$nPag);
?>
<style>
	.flickity-slider {
		width: calc(100% - 15px) !important;
		margin: 7.5px;
	}
	.flickity-page-dots {
		bottom: -30px !important;
	}
</style>
<div id="appContainer" class="w-rounded-15 cm-pad-7-5 cm-pad-0-h <? if($np > 1){?> cm-pad-50-b <?}?> background-white-transparent-50 large-12 medium-12 small-12 w-rounded-25 w-shadow-2 backdrop-blur">
	<div class="js-flickity carousel large-12 medium-12 small-12 clearfix"  data-flickity='{ "cellAlign": "left", "percentPosition": false, "prevNextButtons": false, "pageDots": <? if($np == 1){?> false <?}else{?> true <?} ?>, "fullscreen": false }'>
	 <?
		for($a = 1; $a <= $np; $a++){			
			$kb = ($a * $nPag) - 1;
			$ka = ($kb - ($nPag - 1));
			if($kb > $arrQtd){
				$kb = ($arrQtd - 1);
			}		
			?>
			<div class="large-12 medium-12 small-12 clearfix cm-pad-7-5-b">
				<?				
				for($i = $ka; $i <= $kb; $i++){
					if(array_key_exists($i, $arr)){
						$app = $arr[$i];
						$url = '/app/index.php?valor='.$app['nm'];
						?>				
						<div title="<?= $app['tt'] ?>" onclick="newWindow('<?= $url ?>', '<?= $app['id'] ?>', btoa(encodeURIComponent('<?= $app['im'] ?>')), '<?= $app['tt'] ?>')" class="large-3 medium-3 small-4 float-left cm-pad-7-5 w-color-bl-to-or pointer">						
							<div class="large-12 w-square position-relative">
								<div class="w-rounded-20 w-square-content w-shadow-1 pointer position-relative" style="background: url(<? if($app['im'] == ''){?>https://workz.com.br/images/no-image.jpg<?}else{?>data:image/jpeg;base64,<? echo $app['im']; } ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">						
								</div>
							</div>
							<p class="text-ellipsis text-center fs-d" style="margin-top: 2.5px"><?= $app['tt'] ?></p>						
						</div>
						<?
					}						
				}
				?>				
			</div>
			<?
		}
		?>	
	</div>
</div>