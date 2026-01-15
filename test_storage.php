<?php
require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;

function envv($k, $default = null) {
  $v = getenv($k);
  return ($v === false || $v === '') ? $default : $v;
}

$endpoint = envv('OCI_S3_ENDPOINT');
$region   = envv('OCI_REGION', 'sa-vinhedo-1');
$bucket   = envv('OCI_BUCKET');
$pathStyle= filter_var(envv('OCI_S3_PATH_STYLE', 'true'), FILTER_VALIDATE_BOOLEAN);

if (!$endpoint || !$bucket) {
  fwrite(STDERR, "Missing OCI_S3_ENDPOINT or OCI_BUCKET\n");
  exit(1);
}

$client = new S3Client([
  'version' => 'latest',
  'region'  => $region,
  'endpoint'=> $endpoint,
  'credentials' => [
    'key'    => envv('OCI_S3_ACCESS_KEY'),
    'secret' => envv('OCI_S3_SECRET_KEY'),
  ],
  'use_path_style_endpoint' => $pathStyle,
]);

$key  = 'test/ping.txt';
$body = "pong " . date('c') . "\n";

echo "PUT s3://{$bucket}/{$key}\n";
$client->putObject([
  'Bucket' => $bucket,
  'Key'    => $key,
  'Body'   => $body,
  'ContentType' => 'text/plain; charset=utf-8',
]);

echo "PRESIGN GET 60s\n";
$cmd = $client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
$req = $client->createPresignedRequest($cmd, '+60 seconds');
$url = (string)$req->getUri();

echo $url . PHP_EOL;
