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
$identifiers = explode(',', $options['l']);
$selfIdentifier = $options['t'];

$logger = new Logger($selfIdentifier);
$formatter = new LineFormatter('[%datetime%] %channel%: %message%');
$handler = new ErrorLogHandler();
$handler->setFormatter($formatter);
$logger->pushHandler($handler);

$loop = Factory::create();
$client = new HttpClient($loop);

$gCounter = new GCounter($selfIdentifier, $identifiers);

$app = new App($loop, $gCounter, $client, $logger);
$app->init($selfIdentifier, $identifiers);
