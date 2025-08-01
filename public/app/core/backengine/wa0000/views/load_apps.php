<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

$categoryId = $_GET['vr'];

echo $categoryId;

$apps = search('app', 'apps', '', "tp = '{$categoryId}'");

foreach ($apps as $app) {
    echo "<div class='card'>
        <img src='data:image/png;base64,{$app['im']}' alt='{$app['tt']}'>
        <h2>{$app['tt']}</h2>
        <p>{$app['ab']}</p>
    </div>";
}
?>
