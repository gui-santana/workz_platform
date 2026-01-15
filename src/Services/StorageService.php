<?php

namespace Workz\Platform\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class StorageService
{
    private S3Client $client;
    private string $bucket;
    private int $presignTtl;

    public function __construct(array $config = [])
    {
        $namespace = $config['namespace'] ?? ($_ENV['OCI_S3_NAMESPACE'] ?? '');
        $region = $config['region'] ?? ($_ENV['OCI_S3_REGION'] ?? '');
        $endpoint = $config['endpoint'] ?? ($_ENV['OCI_S3_ENDPOINT'] ?? '');
        $accessKey = $config['access_key'] ?? ($_ENV['OCI_S3_ACCESS_KEY'] ?? '');
        $secretKey = $config['secret_key'] ?? ($_ENV['OCI_S3_SECRET_KEY'] ?? '');
        $this->bucket = $config['bucket'] ?? ($_ENV['OCI_BUCKET'] ?? '');
        $this->presignTtl = (int)($config['presign_ttl'] ?? ($_ENV['OCI_PRESIGN_TTL'] ?? 3600));

        if ($endpoint === '') {
            $namespace = $namespace !== '' ? $namespace : 'axgwwavxlzco';
            $region = $region !== '' ? $region : 'sa-vinhedo-1';
            $endpoint = sprintf(
                'https://%s.compat.objectstorage.%s.oci.customer-oci.com',
                $namespace,
                $region
            );
        }
        if ($region === '') {
            $region = 'sa-vinhedo-1';
        }

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
            'use_path_style_endpoint' => true,
            'signature_version' => 'v4',
        ]);
    }

    public function putObject(string $key, string $localFile, string $contentType): string
    {
        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $params = [
                    'Bucket' => $this->bucket,
                    'Key' => ltrim($key, '/'),
                    'SourceFile' => $localFile,
                    'ContentType' => $contentType,
                ];
                $this->client->putObject($params);
                return ltrim($key, '/');
            } catch (AwsException $e) {
                $lastError = $e;
                error_log(sprintf('StorageService putObject attempt %d failed: %s', $attempt, $e->getMessage()));
                if ($attempt < $maxAttempts) {
                    sleep($attempt);
                }
            }
        }

        throw new \RuntimeException('Failed to upload object to storage: ' . ($lastError?->getMessage() ?? 'unknown error'));
    }

    public function presignGet(string $key, ?int $ttlSeconds = null): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => ltrim($key, '/'),
        ]);
        $ttl = $ttlSeconds ?? $this->presignTtl;
        $request = $this->client->createPresignedRequest($cmd, '+' . $ttl . ' seconds');
        return (string)$request->getUri();
    }

    public function deleteObject(string $key): bool
    {
        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => ltrim($key, '/'),
                ]);
                return true;
            } catch (AwsException $e) {
                $lastError = $e;
                error_log(sprintf('StorageService deleteObject attempt %d failed: %s', $attempt, $e->getMessage()));
                if ($attempt < $maxAttempts) {
                    sleep($attempt);
                }
            }
        }

        throw new \RuntimeException('Failed to delete object from storage: ' . ($lastError?->getMessage() ?? 'unknown error'));
    }
}
