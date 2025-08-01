<html class="no-js" lang="pt-br">
<?
session_start();
require_once($_SERVER['DOCUMENT_ROOT'].'/config/bd.php');
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');

if(!empty($_GET['post'])){	
	$post_consult = $hnw->prepare("SELECT * FROM hpl WHERE id = '".$_GET['post']."'");
	$post_consult->execute();
	$post_count = $post_consult->rowCount(PDO::FETCH_ASSOC);
	$post_result = $post_consult->fetch(PDO::FETCH_ASSOC);
	
	$content = bzdecompress(base64_decode($post_result['ct']));
	
	//header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
	header('Content-Type: application/octet-stream');
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	//header("content-disposition: attachment;filename=WorkzPost.doc");
	header("Content-Disposition: attachment; filename=\"{$post_result['tt']}.doc\"");
	header('Content-Transfer-Encoding: binary');						
	header('Pragma: public');
	
	?>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">		
		<meta charset="utf-8"/>
		<style>
			html{
				font-family: 'Calibri', sans-serif;
			}
			html, body{
				padding: 0;
				margin: 0;
			}
		</style>
	</head>
	<body>	
	<!-- CABEÇALHO -->
	<div style="width: 100%; text-align: center;">
		<h1><? echo $post_result['tt'];  ?></h1>
		<br>
		<?		
		if($post_result['im'] <> ''){
			$newheight = 300;			
			if(substr($post_result['im'],0,15) == '/uploads/posts/'){
				$uri = 'https://workz.com.br'.$post_result['im'];				
			}else{
				$uri = base64_decode($post_result['im']);				
			}
			list($originalwidth, $originalheight) = getimagesize($uri);
			$ratio = $originalheight / $newheight;
			if($ratio > 0){
			$newwidth = $originalwidth / $ratio;
			?>
			<img height="300" width="<? echo str_replace(',', '.', $newwidth); ?>" src="<? echo $uri; ?>" alt="image" />
			<br>
			<?
			}			
		}		
		?>			
		<p><strong><? echo $post_result['ci']; ?></strong></p>
		<p><strong>(<? echo ucfirst(strftime('%B / %Y', strtotime($post_result['dt']))); ?>)</strong></p>
	</div>
	
	<div style="width: 100%;">
	<br>
	<hr/>
	<?
	echo $content;
	?>	
	</div>
	<br/>
	<div style="color: gray;">
		<small>Conteúdo elaborado no Documentos, um aplicativo original Workz. <?if($post_result['st'] > 0){?> Clique <a href="https://workz.com.br?post=<? echo $_GET['post']; ?>">aqui</a> para acessar a este conteúdo online.<?}?></small>
	</div>
	<?
	$hnw = null;
}
?>
</body>
</html>