<?php
include('../../../sanitize.php');
include($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');	
require($_SERVER['DOCUMENT_ROOT'] . '/functions/getAddress.php');

// Configurações de data, hora e locale
setlocale(LC_TIME, 'pt_BR.utf-8');
setlocale(LC_ALL, 'pt_BR.utf-8');
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');
date_default_timezone_set('America/Fortaleza');
$now = date('Y-m-d H:i:s');

if(isset($_POST)){
	
	$im = '';
	$ci = '';
	
	//SAVE POST CONTENT
	if(!empty($_POST['id'])){
		
		if(isset($_POST['ct'])){
			$_POST['ct'] = base64_encode(bzcompress($_POST['ct']));
			if(update('hnw', 'hpl', "ct = '".$_POST['ct']."', dc = '".date('Y-m-d H:i:s')."'", "id = '".$_POST['id']."'")){
				echo 'Artigo salvo com sucesso.';
			}
		}elseif(isset($_POST['st'])){
			if(update('hnw', 'hpl', "st = '".abs($_POST['st'] - 1)."', dt = '".date('Y-m-d H:i:s')."'", "id = '".$_POST['id']."'")){
				echo 'Status alterado com sucesso.';
			}
		}
	
	//POST INFOS
	}elseif(!empty($_POST['vr'])){
		
		//PRESETS
		session_start();					
		$vr = json_decode($_POST['vr'],true);							
		
		//POST ALREADY EXISTS
		
		if(array_key_exists('id', $vr)){
			include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
			$myPost = search('hnw', 'hpl', 'tt,tp,im,cm,ca,ci,kw,st,lg', "id = '".$vr['id']."'")[0];				
			
			$id = $vr['id'];
			$st = $myPost['st'];
			$nChanges = 0;							
			
			foreach($vr as $key => $value){
				if($key == 'imTxt'){
					if($value !== $myPost['im']){
						update('hnw', 'hpl', "im = '".$value."'", "id = '".$id."'");						
						//UPDATE IMAGE						
						$nChanges++;
					}
				}elseif($key !== 'id'){
					if($value !== $myPost[$key]){
						if($key !== 'im' && $key !== 'imTxt'){
														
							if($key == 'cm'){
								
								//Ex.: "cm,1" or "em,2"
								$cm = explode(',',$value);
																
								//Vincula a um perfil de negócio
								if($cm[0] == 'em'){										
									update('hnw', 'hpl', "cm = 0, em = {$cm[1]}", "id = '{$id}'");										
									
								//Vincula a um perfil de equipe
								}elseif($cm[0] == 'cm'){
									update('hnw', 'hpl', "em = 0, cm = {$cm[1]}", "id = '{$id}'");										
								
								//Vincula ao perfil do usuráio
								}else{					
									update('hnw', 'hpl', "cm = 0, em = 0", "id = '".$id."'");
								}
							
							//Atualiza os demais valores
							}elseif($key == 'st'){								
								update('hnw', 'hpl', "st = '".$value."', dt = '".date('Y-m-d H:i:s')."'", "id = '".$id."'");								
							}else{
								//UPDATE KEY VALUE
								update('hnw', 'hpl', $key." = '".$value."'", "id = '".$id."'");
								$$key = $value;
							}								
							$nChanges++;
						}
					}
				}
			}
			
			echo $nChanges;
			
			if($nChanges > 0){
				update('hnw', 'hpl', "dc = '".date('Y-m-d H:i:s')."'", "id = '".$id."'");					
				?>
				<script>
				(function(){						
					closeInstance();						
					goPost('core/backengine/wa0006/tab_content.php', 'tab', <?= $id ?>, '');
					//toggleSidebar();
				})();						
				</script>
				<?php
			}
			
		//NEW POST
		}else{
			
			include($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');								
			if(isset($_GET['type'])){$tp = $_GET['type'];}else{$tp = '';}								
			if(isset($vr['ca'])){$ca = $vr['ca'];}else{$ca = '';}		
			
			$lg = 0;
			$em = '';
			$cm = '';
			if(isset($vr['cm'])){
				$value = explode(',',$vr['cm']);
				if($value[0] == 'em'){
					$cm = '';
					$em = $value[1];
				}elseif($value[0] == 'cm'){
					$cm = $value[1];
					$em = '';
				}
			}
			if(isset($vr['lg'])){
				$lg = $vr['lg'];
			}
			
			if(isset($vr['imTxt'])){$im = $vr['imTxt'];}else{$im = '';}
			if(isset($vr['tt'])){$tt = $vr['tt'];}else{$tt = '';}
			if(isset($vr['kw'])){$kw = $vr['kw'];}else{$kw = '';}				
			
			$dc = date('Y-m-d H:i:s');			
			$st	= 0; //Salvo. Privado.				
							
			if($id = insert('hnw', 'hpl', 'us, tp, ca, dc, ci, cm, em, im, tt, kw, st, lg', "'".$_SESSION['wz']."', '".$tp."','".$ca."','".$dc."','".$ci."','".$cm."','".$em."','".$im."','".$tt."','".$kw."','".$st."', '".$lg."'")){
				?>
				<script>
				(function(){						
					closeInstance();						
					goPost('core/backengine/wa0006/tab_content.php', 'tab', <?= $id ?>, '');
					toggleSidebar();
				})();						
				</script>
				<?php
			}				
		}					
	}		
}
?>