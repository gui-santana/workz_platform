<?php
function sanitizeFileName($text) {
	// Remover espaços excessivos e trimar espaços no início e fim
	$text = trim($text);

	// Remover acentos e caracteres especiais
	$text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

	// Substituir espaços e outros caracteres por underscore
	$text = preg_replace('/[^A-Za-z0-9]+/', '_', $text);

	// Remover underscores duplicados
	$text = preg_replace('/_+/', '_', $text);

	// Converter para minúsculas
	$text = strtolower($text);

	// Limitar tamanho do nome do arquivo (opcional)
	$text = substr($text, 0, 100);

	// Garantir que não termina com underscore
	$text = rtrim($text, '_');

	return $text;
}
?>