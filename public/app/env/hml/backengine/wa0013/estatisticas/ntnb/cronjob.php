<?php
setlocale(LC_ALL, "pt_BR", "pt_BR.iso-8859-1", "pt_BR.utf-8", "portuguese");
date_default_timezone_set('America/Sao_Paulo');

// URL do arquivo que queremos baixar
$url = 'https://cdn.tesouro.gov.br/sistemas-internos/apex/producao/sistemas/sistd/'.date('Y').'/NTN-B_'.date('Y').'.xls';

// Caminho completo da pasta onde desejamos salvar o arquivo
$pastaDestino = $_SERVER['DOCUMENT_ROOT'].'/app/core/backengine/wa0013/estatisticas/ntnb/';

// Nome do arquivo de destino
$nomeArquivo = 'NTN-B_'.date('Y').'.xls';

// Concatenamos o caminho completo com o nome do arquivo para formar o caminho de destino
$caminhoCompleto = $pastaDestino . $nomeArquivo;

// Verificamos se o arquivo já existe
if (file_exists($caminhoCompleto)) {
    // Excluímos o arquivo existente
    unlink($caminhoCompleto);
}

// Usamos a função file_get_contents() para obter o conteúdo do arquivo a partir da URL
$arquivo = file_get_contents($url);

if ($arquivo !== false) {
    // Usamos a função file_put_contents() para salvar o arquivo na pasta de destino
    $salvou = file_put_contents($caminhoCompleto, $arquivo);

    if ($salvou !== false) {
        echo 'O arquivo foi baixado e substituído com sucesso em: ' . $caminhoCompleto;
    } else {
        echo 'Ocorreu um erro ao salvar o arquivo no servidor.';
    }
} else {
    echo 'Ocorreu um erro ao baixar o arquivo do Tesouro Nacional.';
}
?>