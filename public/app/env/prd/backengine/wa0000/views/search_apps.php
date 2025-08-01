<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

$query = $_GET['vr'] ?? '';

if (empty($query)) {
    echo "<p>Digite algo para buscar aplicativos.</p>";
    exit;
}

// Simula busca no banco de dados
$apps = search('app', 'apps', '', "tt LIKE '%$query%'");


if (empty($apps)) {
    echo "<p>Nenhum aplicativo encontrado para a busca '$query'.</p>";
} else {
    foreach ($apps as $app) {
         echo "<div class='card'>
				<img src='data:image/png;base64,{$app['im']}' alt='{$app['tt']}'>
				<h2>{$app['tt']}</h2>
				<p>{$app['ab']}</p>
			</div>";
    }
}
