<?php

namespace Workz\Platform\Controllers;

/**
 * Simple link preview controller to extract Open Graph data or build video embeds.
 */
class LinkPreviewController
{
    public function preview(?object $payload): void
    {
        header('Content-Type: application/json');

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $rawUrl = trim((string)($input['url'] ?? ''));
        $url = $this->normalizeUrl($rawUrl);

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'URL inválida.']);
            return;
        }

        // 1) Tenta identificar plataformas de vídeo suportadas
        $video = $this->detectVideoProvider($url);
        if ($video) {
            $meta = $this->fetchOEmbedMetadata($video['provider'], $url);
            $preview = [
                'kind' => 'video',
                'provider' => $video['provider'],
                'url' => $url,
                'embedUrl' => $video['embedUrl'],
                'title' => $meta['title'] ?? null,
                'description' => $meta['description'] ?? null,
                'image' => $meta['image'] ?? null,
                'siteName' => $meta['siteName'] ?? ucfirst($video['provider']),
                'thumbnail' => $meta['thumbnail'] ?? null,
            ];

            echo json_encode(['status' => 'success', 'kind' => 'video', 'preview' => $preview]);
            return;
        }

        // 2) Conteúdo externo (notícias/artigos)
        $html = $this->fetchHtml($url);
        if ($html === null) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível carregar o link informado.']);
            return;
        }

        $preview = [
            'kind' => 'article',
            'provider' => $this->extractHost($url),
            'url' => $url,
            'title' => $this->extractOg($html, 'title') ?? $this->extractTitleTag($html),
            'description' => $this->extractOg($html, 'description'),
            'image' => $this->extractOg($html, 'image'),
            'siteName' => $this->extractOg($html, 'site_name') ?? $this->extractHost($url),
        ];

        echo json_encode(['status' => 'success', 'kind' => 'article', 'preview' => $preview]);
    }

    private function normalizeUrl(string $url): string
    {
        if (!$url) {
            return '';
        }
        // Prepend https:// when scheme omitted
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    private function extractHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return strtolower($host);
    }

    private function detectVideoProvider(string $url): ?array
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($query, $params);

        // YouTube (youtube.com / youtu.be)
        if (strpos($host, 'youtube.com') !== false || $host === 'youtu.be') {
            $id = $params['v'] ?? '';
            if (!$id && $host === 'youtu.be') {
                $parts = array_values(array_filter(explode('/', trim($path, '/'))));
                $id = $parts[0] ?? '';
            }
            if (!$id && strpos($path, '/shorts/') !== false) {
                $chunks = explode('/shorts/', $path);
                $id = $chunks[1] ?? '';
            }
            $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
            if ($id) {
                return [
                    'provider' => 'youtube',
                    'id' => $id,
                    'embedUrl' => 'https://www.youtube.com/embed/' . $id,
                ];
            }
        }

        // Vimeo
        if (strpos($host, 'vimeo.com') !== false) {
            $parts = array_values(array_filter(explode('/', trim($path, '/'))));
            $id = end($parts);
            if ($id && ctype_digit($id)) {
                return [
                    'provider' => 'vimeo',
                    'id' => $id,
                    'embedUrl' => 'https://player.vimeo.com/video/' . $id,
                ];
            }
        }

        // Dailymotion
        if (strpos($host, 'dailymotion.com') !== false || $host === 'dai.ly') {
            $id = '';
            if (strpos($path, '/video/') !== false) {
                $chunks = explode('/video/', $path);
                $id = $chunks[1] ?? '';
            } else {
                $parts = array_values(array_filter(explode('/', trim($path, '/'))));
                $id = $parts[0] ?? '';
            }
            $id = preg_replace('/[^a-zA-Z0-9]/', '', $id);
            if ($id) {
                return [
                    'provider' => 'dailymotion',
                    'id' => $id,
                    'embedUrl' => 'https://www.dailymotion.com/embed/video/' . $id,
                ];
            }
        }

        // Canva (modo embed)
        if (strpos($host, 'canva.com') !== false && strpos($path, '/design/') !== false) {
            $cleanPath = rtrim($path, '/');
            return [
                'provider' => 'canva',
                'id' => basename($cleanPath),
                'embedUrl' => $url . ((strpos($url, '?') !== false) ? '&' : '?') . 'embed',
            ];
        }

        return null;
    }

    private function fetchOEmbedMetadata(string $provider, string $url): array
    {
        $endpoints = [
            'youtube' => 'https://www.youtube.com/oembed?format=json&url=',
            'vimeo' => 'https://vimeo.com/api/oembed.json?url=',
            'dailymotion' => 'https://www.dailymotion.com/services/oembed?format=json&url=',
        ];

        if (!isset($endpoints[$provider])) {
            return [];
        }

        $json = $this->fetchJson($endpoints[$provider] . urlencode($url));
        if (!$json) {
            return [];
        }

        return [
            'title' => $this->sanitize($json['title'] ?? ''),
            'description' => $this->sanitize($json['description'] ?? ''),
            'image' => $json['thumbnail_url'] ?? null,
            'thumbnail' => $json['thumbnail_url'] ?? null,
            'siteName' => $this->sanitize($json['author_name'] ?? ''),
        ];
    }

    private function fetchJson(string $url): ?array
    {
        $resp = $this->fetchRaw($url, 5000);
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    private function fetchHtml(string $url): ?string
    {
        return $this->fetchRaw($url, 6000);
    }

    private function fetchRaw(string $url, int $timeoutMs = 5000): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_USERAGENT => 'WorkzLinkPreview/1.0 (+https://www.workz.com)',
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return null;
        }

        // Limita a resposta para evitar sobrecarga
        return substr((string)$response, 0, 400000);
    }

    private function extractOg(string $html, string $property): ?string
    {
        $pattern = sprintf('/<meta[^>]+property=[\"\\\']og:%s[\"\\\'][^>]+content=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i', preg_quote($property, '/'));
        if (preg_match($pattern, $html, $matches)) {
            return $this->sanitize($matches[1]);
        }
        return null;
    }

    private function extractTitleTag(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return $this->sanitize($matches[1]);
        }
        return null;
    }

    private function sanitize(?string $value): string
    {
        $clean = trim((string)$value);
        $clean = strip_tags($clean);
        return html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
