<?php
function isBase64($string) {
	// Remove espaços em branco e quebras de linha (base64 pode conter isso)
	$cleanedString = preg_replace('/\s+/', '', $string);

	// Base64 deve conter apenas caracteres válidos
	if (!preg_match('/^[a-zA-Z0-9\/+]*={0,2}$/', $cleanedString)) {
		return false;
	}

	// Base64 deve ter comprimento múltiplo de 4 (exceto se truncado no final)
	if (strlen($cleanedString) % 4 !== 0) {
		return false;
	}

	// Decodifica e valida que o conteúdo decodificado é válido
	$decoded = base64_decode($cleanedString, true);
	return $decoded !== false;
}
?>