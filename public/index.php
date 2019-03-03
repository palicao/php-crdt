<?php

error_reporting(E_ALL);

require __DIR__ . "/../vendor/autoload.php";

use CRDT_GCounter\App;
use CRDT_GCounter\GCounter;
use React\EventLoop\Factory;
use React\HttpClient\Client as HttpClient;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\LineFormatter;

$options = getopt('l:t:');
$ports = explode(',', $options['l']);
$thisPort = $options['t'];

$logger = new Logger($thisPort);
$formatter = new LineFormatter("[%datetime%] %channel%: %message%");
$handler = new ErrorLogHandler();
$handler->setFormatter($formatter);
$logger->pushHandler($handler);

$loop = Factory::create();
$client = new HttpClient($loop);

$gcounter = new GCounter($thisPort, $ports);

$app = new App($loop, $gcounter, $client, $logger);
$app->init($thisPort, $ports);
