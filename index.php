<?php


use Duo\Scan\Command\DeepScanModuleCommand;
use Duo\Scan\Command\ScanCommand;
use Duo\Scan\Command\UpdateModuleListCommand;
use GuzzleHttp\Client;
use Symfony\Component\Console\Application;

require_once __DIR__ . '/vendor/autoload.php';

$application = new Application();
$application->add(new ScanCommand());
$application->add(new DeepScanModuleCommand());
$application->add(new UpdateModuleListCommand());
try {
    $application->run();
} catch (Exception $e) {
    echo "SCANNING IS ILLEGAL WE TRACKED YOUR IP AND THE FBI IS UNDERWAY!";
}
