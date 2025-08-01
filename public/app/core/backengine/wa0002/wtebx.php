<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
include('../../../sanitize.php');
require_once $_SERVER['DOCUMENT_ROOT'] . 'app/core/backengine/tools/mpdf/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . 'functions/search.php';

if (!class_exists(\Mpdf\Mpdf::class)) {
    die('A classe Mpdf não foi encontrada. Verifique sua instalação.');
}
if (!class_exists(\Mpdf\HTMLParserMode::class)) {
    die('A classe HTMLParserMode não foi encontrada. Verifique sua instalação.');
}

setlocale(LC_TIME, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_GET['vr'])) {
    die('ID da empresa não fornecido.');
}

$office_id = $_GET['vr'];
$dtch = isset($_GET['qt']) ? date('Y-m-t', strtotime($_GET['qt'])) : date('Y-m-t');

use Mpdf\Mpdf;
use \Mpdf\HTMLParserMode;

try {
    $mpdf = new Mpdf();
    echo "mPDF versão compatível carregado com sucesso!";
} catch (\Mpdf\MpdfException $e) {
    die('Erro ao carregar mPDF: ' . $e->getMessage());
}

// Carregar CSS
$cssPath = $_SERVER['DOCUMENT_ROOT'] . '/app/core/backengine/tools/mpdf/css/estilo.css';
if (!file_exists($cssPath)) {
    die('Arquivo de estilo não encontrado.');
}
$css = file_get_contents($cssPath);
$mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);


// Cabeçalho
$header = '
<div style="font-family: arial; width: 100%; margin-bottom: 7.5px; height: 25px;">
    <div style="width: 25px; float: left;">
        <img height="25px" src="https://workz.com.br/images/workz.png"></img>
    </div>
    <div style="width: calc(100% - 25px); float: left; padding: 2.5px 0 0 20px; font-weight: 600; text-transform: uppercase;">
        <a>Extrato de Movimentação Financeira | <strong>' . strtoupper(utf8_encode(strftime('%B/%Y', strtotime($dtch)))) . '</strong></a>
    </div>
    <div style="clear: both"></div>
</div>
<hr style="color: #FCA988; height: 2px;">
';
$mpdf->WriteHTML($header, HTMLParserMode::HTML_BODY);

// Obter lista de empresas
$scds = isset($_GET['sc']) ? [$_GET['sc']] : array_column(search('cmp', 'companies_groups', 'emC', "emP = '{$office_id}'"), 'emC');

if (empty($scds)) {
    die('Nenhuma empresa encontrada.');
}


foreach ($scds as $scid) {
    $sc = search('cmp', 'companies', '', "id = '{$scid}'");
    if (empty($sc)) {
        echo '<p>Empresa não encontrada para o ID ' . $scid . '.</p>';
        continue;
    }

    $regs = search('app', 'wa0002_regs_alterado', '', "lgtp = '0' AND dtch = '{$dtch}' AND scid = '{$scid}' AND lgst = '0'");
    if (empty($regs)) {
        echo '<p>Não há registros para a empresa com ID ' . $scid . '.</p>';
        continue;
    }

    foreach ($regs as $reg) {
        $usr = $reg['lgus'];
        $dds = explode(';', $reg['vlch']);

        // Processar campos de dados
        $pri = $dds[0] ?? 'N/A';
        $jur = $dds[1] ?? 'N/A';
        $jac = $dds[2] ?? 'N/A';
        $vmn = $dds[3] ?? 'N/A';
        $scp = $dds[4] ?? 'N/A';
        $slp = $dds[5] ?? 'N/A';
        $trs = $dds[6] ?? 'N/A';

        // Processar moedas
        $umn = isset($dds[7]) ? explode('/', $dds[7]) : [];
        $moedas = [];
        foreach ($umn as $und) {
            $parts = explode('>', $und);
            if (count($parts) >= 3) {
                $moedas[] = '
                <small>- ' . $parts[0] . ' → 1ª Quinz.: R$ ' . number_format($parts[1], 6, ',', '.') . 
                ' / 2ª Quinz.: R$ ' . number_format($parts[2], 6, ',', '.') . '</small><br>';
            }
        }
        $moedas = implode('', $moedas);

        // Processar contratos
        $ctr = isset($dds[8]) ? explode('/', $dds[8]) : [];
        $contratos = [];
        foreach ($ctr as $con) {
            $parts = explode('>', $con);
            if (count($parts) >= 2) {
                $contratos[] = '
                <small>- Contrato ' . $parts[0] . ': ' . $parts[1] . ' Subcréditos</small><br>';
            }
        }
        $contratos = implode('', $contratos);

        // Conteúdo do PDF
        $html = '
        <fieldset class="how">
            <div style="width: 100%;">
                <table style="width: 100%; font-family: arial;">
                    <tr>
                        <td colspan="5" style="margin: 0; padding: 0; text-transform: uppercase; font-size: 12px; font-weight: 600;">' . $sc[0]['tt'] . '<td>
                    </tr>
                    <tr>
                        <th style="width: 20%; font-size: 12px;">Amort. Principal</th>
                        <th style="width: 20%; font-size: 12px;">Juros Compensat.</th>
                        <th style="width: 20%; font-size: 12px;">Juros Acumulados</th>
                        <th style="width: 20%; font-size: 12px;">Variação Monetária</th>
                        <th style="width: 20%; font-size: 12px;">Transfer. L.P. C.P.</th>
                    </tr>
                    <tr>
                        <td style="width: 20%; font-size: 13px;">R$ ' . $pri . '</td>
                        <td style="width: 20%; font-size: 13px;">R$ ' . $jur . '</td>
                        <td style="width: 20%; font-size: 13px;">R$ ' . $jac . '</td>
                        <td style="width: 20%; font-size: 13px;">R$ ' . $vmn . '</td>
                        <td style="width: 20%; font-size: 13px;">R$ ' . $trs . '</td>
                    </tr>
                </table>
                <div style="font-size: 12px;">
                    <p style="color: #000;">Detalhes da apuração:</p>
                    <div style="margin-top: 5px;">
                        <div style="width: 50%; float: left;">
                            <small>Cotações:</small><br>' . $moedas . '
                            <small>Contrato(s):</small><br>' . $contratos . '
                        </div>
                        <div style="width: 50%; float: right;">
                            <small>Saldos de Principal:</small><br>
                            <small>- Curto Prazo: R$ ' . $scp . '</small><br>
                            <small>- Longo Prazo: R$ ' . $slp . '</small>
                        </div>
                    </div>
                    <div style="width: 100%; margin-top: 5px;"><small>Registrado por ' . $usr . ' em ' . date('d/m/Y H:i:s', strtotime($reg['lgdt'])) . '</small></div>
                </div>
            </div>
        </fieldset>';
        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
    }
}

// Rodapé
$footer = '
<div class="creditos" style="font-family: arial">
    <small><a href="https://app.workz.com.br/bndes">Workz! BNDES</a> | Relatório gerado em ' . date('d/m/Y H:i:s') . '.</small>
</div>';
$mpdf->SetHTMLFooter($footer);

// Output do PDF
$mpdf->Output('Relatorio_BNDES_' . date('m_Y') . '.pdf', 'I');
exit;
?>