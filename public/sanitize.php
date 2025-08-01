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
?>