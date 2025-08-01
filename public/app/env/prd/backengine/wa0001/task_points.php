<?php
// Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once('../../../sanitize.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
session_start();

$result = search('app', 'wa0001_usxp', 'SUM(xp)', "us = '{$_SESSION['wz']}'");
$totalXP = $result[0]['SUM(xp)'] ?? 0;


?>
<style>
.xp-bar {
	background-color: #F7C045;
	width: <?= ($totalXP / 100) * 100 ?>%;
	height: 100%;
	border-radius: 5px;
}
</style>
<div class="large-6 medium-6 small-12 cm-pad-10-r">
	<div class="large-12 medium-12 small-12">
		<span>✨ <?= $totalXP ?> / 100</span>
	</div>
	<div class="bar"><div class="xp-bar"></div></div>
</div>	

