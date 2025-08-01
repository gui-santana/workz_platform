<?php
/**
 * Função para carregar o conteúdo HTML de uma URL com tratamento de erros.
 *
 * @param string $url
 * @return string
 * @throws Exception
 */
function loadHTMLFileWithErrors(string $url): string {
	$options = [
		'http' => [
			'method' => 'GET',
			'timeout' => 10, // Timeout em segundos
		]
	];
	$context = stream_context_create($options);
	$htmlContent = @file_get_contents($url, false, $context);

	if ($htmlContent === false) {
		throw new Exception("Erro ao acessar a URL: $url");
	}

	return $htmlContent;
}

/**
 * Função para obter o conteúdo do primeiro parágrafo da classe desejada.
 *
 * @param string $htmlContent
 * @param string $query
 * @return string|null
 */
function getFirstParagraphFromHTML(string $htmlContent, string $query): ?string {
	// Cria um objeto DOMDocument
	$dom = new DOMDocument();

	// Suprime erros do DOMDocument
	libxml_use_internal_errors(true);
	if (!$dom->loadHTML($htmlContent)) {
		libxml_clear_errors();
		return null;
	}
	libxml_clear_errors();

	// Cria um objeto DOMXPath
	$xpath = new DOMXPath($dom);

	// Executa a consulta XPath
	$elements = $xpath->query($query);

	if ($elements && $elements->length > 0) {
		return $elements->item(0)->textContent;
	}

	return null;
}


?>