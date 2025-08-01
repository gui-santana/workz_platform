<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$pattern = '/public_html\/([^\/]+)/';	
preg_match($pattern, $documentRoot, $matches);	
$subdomainFolder = isset($matches[1]) ? $matches[1] : '';	
$sanitizedFolder = preg_replace('/[^a-zA-Z0-9-_]/', '', $subdomainFolder);
$currentUrl = $_SERVER['HTTP_HOST'];
$parts = explode('.', $currentUrl);
$subdomain = $parts[0];						
if ($sanitizedFolder === $subdomain){
	if(strpos($documentRoot, $sanitizedFolder.'/') > 0){
		$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder.'/', '', $documentRoot);					
	}else{
		$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder, '', $documentRoot);
	}			
}
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
if(isset($_GET['app'])) {
    $app = search('app', 'apps', '', "nm = '".$_GET['app']."'");
    if(count($app) > 0){
        $imagem_base64 = $app[0]['im'];        
        if(strpos($imagem_base64, 'data:image/') === 0){
            $imagem_base64 = 'data:image/png;base64,'.$imagem_base64;
        }
        // Defina o tipo de conteúdo como uma imagem
        header('Content-Type: image/png');       
        // Envie a imagem
        echo base64_decode($imagem_base64);
    } else {
        header('Content-Type: text/plain');
        echo "Imagem não encontrada";
    }
} else {
    header('Content-Type: text/plain');
    echo "Parâmetro 'app' não especificado";
}
$conn->close();
?>

