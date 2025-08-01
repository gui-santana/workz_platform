<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
session_start();

$result = search('app', 'wa0001_usxp', 'SUM(xp)', "us = '{$_SESSION['wz']}'");
$totalXP = $result[0]['SUM(xp)'] ?? 0;

function calcularNivel($xpTotal) {
    $xpBase = 100; // XP necessário para alcançar o nível 1
    $fatorCrescimento = 2; // Define a progressão exponencial (1.5, 2.0, etc.)

    $nivel = 1; // Começa no nível 1
    $xpNecessario = $xpBase; // XP necessário para alcançar o próximo nível

    while ($xpTotal >= $xpNecessario) {
        $xpTotal -= $xpNecessario; // Reduz o XP total pelo necessário para esse nível
        $nivel++; // Aumenta o nível
        $xpNecessario = ceil($xpBase * pow($fatorCrescimento, $nivel - 1)); // Define o XP necessário para o próximo nível
    }

    // Progresso dentro do nível atual
    $progresso = round(($xpTotal / $xpNecessario) * 100, 2); // Percentual de progresso no nível atual

    return [
        'nivel' => $nivel,
        'xpAtual' => $xpTotal,
        'xpNecessario' => $xpNecessario,
        'progresso' => $progresso
    ];
}

$dadosNivel = calcularNivel($totalXP);

?>
<style>
.xp-bar {
	width: <?= ($dadosNivel['xpAtual'] / $dadosNivel['xpNecessario']) * 100 ?>%;
	height: 100%;
	border-radius: 5px;
}
</style>
<div class="large-6 medium-6"></div>
<div class="large-6 medium-6 small-12 cm-pad-10-r">
	<p><?= "Nível " . $dadosNivel['nivel'] ?></p>
	<div class="bar">
		<div class="background-yellow xp-bar"></div>
	</div>
	<p class="text-right"><i class="fas fa-star yellow"></i> <?= number_format($dadosNivel['xpAtual'], 2, ',', '.') ?> / <?= number_format($dadosNivel['xpNecessario'], 0, ',', '.') ?></p>
</div>	

