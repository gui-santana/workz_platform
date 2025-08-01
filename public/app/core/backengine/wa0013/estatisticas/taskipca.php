<?php
$ftp_host = 'ftp.ibge.gov.br';
$ftp_user = 'anonymous';
$ftp_password = '';

// Conectando ao FTP
echo "<br />Connecting to $ftp_host via FTP...";
$conn = ftp_connect($ftp_host);

// Verificando se a conexão foi bem-sucedida
if (!$conn) {
    die("FTP connection has failed!");
}

// Logando no servidor FTP
$login = ftp_login($conn, $ftp_user, $ftp_password);
if (!$login) {
    die("FTP login has failed!");
}

echo "<br />Login Ok.<br />";

// Habilitar modo passivo (após o login)
ftp_set_option($conn, FTP_USEPASVADDRESS, false);
$mode = ftp_pasv($conn, true);
if (!$mode) {
    die("Failed to enable passive mode!");
}

// Listando arquivos no diretório
$file_list = ftp_nlist($conn, "");
if ($file_list === false) {
    die("Error listing files from FTP server.");
}

foreach ($file_list as $file) {
    echo "<br>$file";
}

// Definindo caminhos dos arquivos local e no servidor
$local_file = "/home/u796300692/domains/workz.com.br/public_html/app/core/backengine/wa0013/estatisticas/download/ipca.zip";
$server_file = "/Precos_Indices_de_Precos_ao_Consumidor/IPCA/Serie_Historica/ipca_SerieHist.zip";

// Fazendo o download do arquivo
if (ftp_get($conn, $local_file, $server_file, FTP_BINARY)) {
    echo "<br>Successfully written to $local_file.";
} else {
    echo "<br>Error downloading $server_file.";
}

// Fechando a conexão FTP
ftp_close($conn);

// Abrindo e extraindo o arquivo zip
$zip = new ZipArchive;
if ($zip->open($local_file) === true) {
    if ($zip->extractTo('/home/u796300692/domains/workz.com.br/public_html/app/core/backengine/wa0013/estatisticas/download/')) {
        echo "<br>Zip file extracted successfully.";
    } else {
        echo "<br>Error extracting zip file!";
    }
    $zip->close();
} else {
    echo "<br>Error reading zip-archive!";
}
?>
