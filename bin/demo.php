#!/usr/bin/php
<?php

// Note that this example requires amphp/http-server-router,
// amphp/http-server-static-content and amphp/log to be installed.

use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Amp\Socket;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;

use App\Chat;

require __DIR__ . '/../vendor/autoload.php';

$websocket = new Chat();

$sockets = [
    Socket\listen('127.0.0.1:8000'),
    Socket\listen('[::1]:8000'),
];

$router = new Router;
$router->addRoute('GET', '/chat', $websocket);
$router->setFallback(new DocumentRoot(__DIR__ . '/../htdocs'));

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$server = new Server($sockets, $router, $logger);

Loop::run(function () use ($server) {
    yield $server->start();
});
