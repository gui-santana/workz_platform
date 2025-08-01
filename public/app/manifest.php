<?php
	//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']	
	include('sanitize.php');
	header('Content-Type: application/manifest+json'); // Define o tipo de conteúdo como JSON
	require_once('../functions/search.php');
	$app = search('app', 'apps', '', "nm = '".$_GET['app']."'");
	$colours = explode(';', $app[0]['cl']);

	if(count($app) > 0){
		// Definindo o manifesto como um array associativo
		$manifest = array(
			"short_name" => $app[0]['tt'],
			"name" => "Workz! " . $app[0]['tt'],
			"orientation" => "portrait",
			"description" => $app[0]['ab'],
			"start_url" => $app[0]['nm'],
			"display" => "standalone",
			"background_color" => $colours[0],
			"theme_color" => $colours[0],
			"id" => $app[0]['nm'],
			"lang" => "pt",
			"categories" => array(
				"navigation",
				"productivity",
				"security",
				"utilities"
			),
			"icons" => array(
				array(
					"src" => "https://app.workz.com.br/imgLogo.php?app=".$app[0]['nm'],
					"sizes" => "512x512",
					"type" => "image/png",
					"purpose" => "any"
				)
			),
			"edge_side_panel" => array(
				"preferred_width" => 0
			),
			"dir" => "ltr", // Requisito: Manifest specifies a default direction of text
			
			"iarc_rating_id" => $app[0]['iarc'], // Requisito: Manifest has iarc_rating_id field (null = livre)
			"prefer_related_applications" => false, // Requisito: Manifest properly sets prefer_related_applications field
			"protocol_handlers" => array( // Requisito: Manifest has protocol_handlers field
				array(
					"protocol" => "web+myprotocol",
					"url" => "/handler?url=%s"
				)
			),
			"related_applications" => array( // Requisito: Manifest has related_applications field
				array(
					"platform" => "play",
					"url" => "https://play.google.com/store/apps/details?id=com.example.app1",
					"id" => "com.example.app1"
				),
				array(
					"platform" => "itunes",
					"url" => "https://itunes.apple.com/app/example-app1/id123456789",
					"id" => "123456789"
				)
			),
			"scope" => "/", // Requisito: Manifest has scope field
			"scope_extensions" => array( // Requisito: Manifest has scope_extensions field
				"/extension/"
			),
			"share_target" => array( // Requisito: Manifest has share_target field
				"action" => "/",
				"method" => "POST",
				"params" => array(
					"title" => "title",
					"text" => "text",
					"url" => "url"
				)
			),
			"shortcuts" => array( // Requisito: Manifest has shortcuts field
				array(
					"name" => "Workz! ".$app[0]['tt'],
					"short_name"  => $app[0]['tt'],
					"description" => $app[0]['ab'],
					"url" => "/",
					"icons" => array(
						array(
							"src" => "data:image/png;base64," . $app[0]['im'],
							"sizes" => "512x512",
							"type" => "image/png"
						)
					)
				)
			),							
			"display_override" => array( 
				"window-controls-overlay",
				"standalone",
				"browser"
			),
			"handle_links" => "preferred", // Requisito: Manifest has handle_links field							
			"launch_handler" => array(
				"client_mode" => "navigate-existing"
			),
			"screenshots" => array( // Requisito: Manifest has screenshots field
				array(
					"src" => "data:image/png;base64," . $app[0]['im'],
					"sizes" => "800x800",
					"type" => "image/jpeg"
				)								
			)
		);
		echo json_encode($manifest, JSON_PRETTY_PRINT); // Converte o array em JSON e o imprime
	}else{
		echo 'Não foi possível obter o manifesto do aplicativo.';
	}
	
?>