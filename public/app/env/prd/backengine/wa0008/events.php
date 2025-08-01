<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

session_start();

if (!isset($_SESSION['accessToken'])) {
    error_log('Missing access token');
    header('Location: /app/core/backengine/wa0008/auth.php');
    exit;
}

error_log('Using access token: ' . $_SESSION['accessToken']);

$accessToken = $_SESSION['accessToken'];

$graph = new Graph();
$graph->setAccessToken($accessToken);

try {
    $events = $graph->createRequest("GET", "/me/events")
                    ->setReturnType(Model\Event::class)
                    ->execute();
    error_log('Received events: ' . print_r($events, true));
} catch (Exception $e) {
    error_log('Error fetching events: ' . $e->getMessage());
    exit('Error fetching events');
}

foreach ($events as $event) {
    echo "Event: " . $event->getSubject() . "<br>";
    echo "Start: " . $event->getStart()->format(DateTime::RFC3339) . "<br>";
    echo "End: " . $event->getEnd()->format(DateTime::RFC3339) . "<br><br>";
}
?>
