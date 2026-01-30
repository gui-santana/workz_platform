<?php
// src/Services/ManifestNormalizer.php

namespace Workz\Platform\Services;

class ManifestNormalizer
{
    public static function buildFromPipe(array $pipe, string $appType = 'javascript', ?string $host = null): array
    {
        $runtime = self::normalizeRuntime($pipe['runtime'] ?? null, $appType);
        $ctxMode = self::normalizeContextMode(
            $pipe['contextRequirements']['mode'] ?? $pipe['context_mode'] ?? $pipe['contextMode'] ?? null
        );
        $allowSwitch = self::normalizeBool(
            $pipe['contextRequirements']['allowContextSwitch']
                ?? $pipe['allow_context_switch']
                ?? $pipe['context_switch']
                ?? $pipe['allowSwitch']
                ?? false
        );

        $apiAllowRaw = $pipe['capabilities']['api']['allow']
            ?? $pipe['api']['allowlist']
            ?? $pipe['api_allowlist']
            ?? $pipe['api_allow']
            ?? [];
        $apiAllow = self::parseApiAllowList($apiAllowRaw);

        $eventsPublishRaw = $pipe['capabilities']['events']['publish']
            ?? $pipe['events']['publish']
            ?? $pipe['events_publish']
            ?? [];
        $eventsSubscribeRaw = $pipe['capabilities']['events']['subscribe']
            ?? $pipe['events']['subscribe']
            ?? $pipe['events_subscribe']
            ?? [];
        $eventsPublish = self::normalizeEventList($eventsPublishRaw, true);
        $eventsSubscribe = self::normalizeEventList($eventsSubscribeRaw, true);

        if (!$eventsPublish) {
            $eventsPublish = ['app:*', 'app:started', 'app:error', 'theme:changed'];
        }
        if (!$eventsSubscribe) {
            $eventsSubscribe = ['app:*', 'app:started', 'app:error', 'theme:changed'];
        }

        $originsRaw = $pipe['sandbox']['postMessage']['allowedOrigins']
            ?? $pipe['allowedOrigins']
            ?? $pipe['allowed_origins']
            ?? [];
        $allowedOrigins = self::normalizeOrigins($originsRaw);
        if (!$allowedOrigins) {
            $allowedOrigins = self::fallbackAllowedOrigins($host);
        }

        $storage = $pipe['capabilities']['storage'] ?? $pipe['storage'] ?? [];
        $kv = self::normalizeBool($storage['kv'] ?? $pipe['kv_enabled'] ?? true);
        $docsEnabled = self::normalizeBool($storage['docs']['enabled'] ?? $storage['docs'] ?? $pipe['docs_enabled'] ?? false);
        $blobs = self::normalizeBool($storage['blobs'] ?? $pipe['blobs_enabled'] ?? false);
        $docsTypesRaw = $storage['docs']['types'] ?? $storage['docsTypes'] ?? $pipe['docs_types'] ?? [];
        $docsTypes = self::normalizeList($docsTypesRaw);
        if ($docsEnabled && !$docsTypes) {
            $docsTypes = ['user_data'];
        }
        $scope = self::normalizeStorageScope($storage['scope'] ?? $pipe['storage_scope'] ?? null);

        $proxySourcesRaw = $pipe['capabilities']['proxy']['sources']
            ?? $pipe['proxy']['sources']
            ?? $pipe['proxy_sources']
            ?? [];
        $proxySources = self::normalizeList($proxySourcesRaw);

        return [
            'runtime' => $runtime,
            'capabilities' => [
                'api' => [
                    'allow' => $apiAllow
                ],
                'events' => [
                    'enabled' => true,
                    'publish' => $eventsPublish,
                    'subscribe' => $eventsSubscribe
                ],
                'storage' => [
                    'kv' => $kv,
                    'docs' => [
                        'enabled' => $docsEnabled,
                        'types' => $docsTypes
                    ],
                    'blobs' => $blobs,
                    'scope' => $scope
                ],
                'proxy' => [
                    'sources' => $proxySources
                ]
            ],
            'contextRequirements' => [
                'mode' => $ctxMode,
                'allowContextSwitch' => $allowSwitch
            ],
            'sandbox' => [
                'postMessage' => [
                    'allowedOrigins' => $allowedOrigins
                ]
            ]
        ];
    }

    private static function normalizeRuntime($runtime, string $appType): string
    {
        $value = is_string($runtime) ? strtolower(trim($runtime)) : '';
        if ($value === 'flutter') return 'flutter';
        if ($value === 'javascript') return 'javascript';
        $type = strtolower(trim($appType));
        return ($type === 'flutter' || $type === 'dart') ? 'flutter' : 'javascript';
    }

    private static function normalizeContextMode($mode): string
    {
        $value = strtolower(trim((string)$mode));
        $allowed = ['user', 'business', 'team', 'hybrid'];
        return in_array($value, $allowed, true) ? $value : 'user';
    }

    private static function normalizeStorageScope($scope): string
    {
        $value = strtolower(trim((string)$scope));
        return ($value === 'user' || $value === 'context') ? $value : 'context';
    }

    private static function normalizeBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int)$value) !== 0;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    private static function normalizeList($input): array
    {
        $items = [];
        if (is_array($input)) {
            $items = $input;
        } elseif (is_string($input)) {
            $items = preg_split("/\r\n|\n|\r/", $input) ?: [];
        }
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                $out[] = $item;
                continue;
            }
            $val = trim((string)$item);
            if ($val === '') continue;
            $out[] = $val;
        }
        $flat = [];
        $seen = [];
        foreach ($out as $val) {
            if (is_array($val) || is_object($val)) {
                $flat[] = $val;
                continue;
            }
            $key = strtolower((string)$val);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $flat[] = $val;
        }
        return array_values($flat);
    }

    private static function normalizeEventList($input, bool $filterReserved = true): array
    {
        $list = self::normalizeList($input);
        $out = [];
        foreach ($list as $item) {
            if (!is_string($item)) continue;
            $val = trim($item);
            if ($val === '') continue;
            if ($filterReserved && self::isReservedEvent($val)) {
                continue;
            }
            $out[] = $val;
        }
        return array_values(array_unique($out));
    }

    private static function isReservedEvent(string $name): bool
    {
        return str_starts_with($name, 'workz-sdk:') || str_starts_with($name, 'sdk:');
    }

    private static function parseApiAllowList($input): array
    {
        $rows = self::normalizeList($input);
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $method = '';
            $path = '';
            if (is_array($row)) {
                $method = strtoupper(trim((string)($row['method'] ?? '')));
                $path = trim((string)($row['path'] ?? ''));
            } elseif (is_string($row)) {
                if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(.+)$/i', $row, $m)) {
                    $method = strtoupper($m[1]);
                    $path = trim($m[2]);
                }
            }
            if ($method === '' || $path === '') continue;
            if ($path[0] !== '/') $path = '/' . $path;
            $key = $method . ' ' . $path;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['method' => $method, 'path' => $path];
        }
        return $out;
    }

    private static function normalizeOrigins($input): array
    {
        $items = self::normalizeList($input);
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_string($item)) continue;
            $val = trim($item);
            if ($val === '' || $val === '*') continue;
            $key = strtolower($val);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $val;
        }
        return array_values($out);
    }

    private static function fallbackAllowedOrigins(?string $host): array
    {
        if (!self::isDevHost($host)) {
            return [];
        }
        $origin = self::originFromHost($host);
        return $origin ? [$origin] : ['http://localhost:9090'];
    }

    private static function isDevHost(?string $host): bool
    {
        $env = strtolower((string)($_ENV['APP_ENV'] ?? $_ENV['ENVIRONMENT'] ?? ''));
        if (in_array($env, ['local', 'dev', 'development'], true)) return true;
        $host = strtolower((string)$host);
        if ($host === '') return false;
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === 'localhost') return true;
        if (str_ends_with($host, '.localhost')) return true;
        if (str_starts_with($host, '127.')) return true;
        return false;
    }

    private static function originFromHost(?string $host): ?string
    {
        $host = trim((string)$host);
        if ($host === '') return null;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}
