<?php

namespace CRDT_GCounter;

use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Http\Response as HttpResponse;
use React\HttpClient\Response as HttpClientResponse;
use React\Socket\Server as SocketServer;
use React\HttpClient\Client as HttpClient;
use Psr\Http\Message\ServerRequestInterface;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use Psr\Log\LoggerInterface;
use function FastRoute\simpleDispatcher;

class App
{
    private $loop;
    private $gCounter;
    private $client;
    private $logger;

    public function __construct(LoopInterface $loop, GCounterInterface $gCounter, HttpClient $client, LoggerInterface $logger)
    {
        $this->loop = $loop;
        $this->gCounter = $gCounter;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Inits the HTTP Server with 3 endpoints (count, get, increment), and a timer
     * which joins another random server at a random interval
     */
    public function init(int $selfIdentifier, array $identifiers): void
    {
        $this->initHttpServer($selfIdentifier);
        $this->initTimer(array_values(array_diff($identifiers, [$selfIdentifier])));
        $this->loop->run();
    }

    private function initHttpServer(int $selfIdentifier): void
    {
        $server = new HttpServer(function (ServerRequestInterface $request) {

            $dispatcher = $this->dispatcher();
            $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

            if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
                return new HttpResponse(404, ['Content-Type' => 'text/plain'], 'Not found');
            }

            if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                return new HttpResponse(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
            }

            return $routeInfo[1]($request);
        });

        // the identifier is also the port which the server listens to
        $socket = new SocketServer($selfIdentifier, $this->loop);
        $server->listen($socket);

        $this->logger->info(sprintf('Server running at http://127.0.0.1:%s', $selfIdentifier));
    }

    private function dispatcher(): Dispatcher
    {
        return simpleDispatcher(function (RouteCollector $routes) {
            $routes->addRoute('GET', '/get', $this->getAllEndpoint());
            $routes->addRoute('POST', '/increment', $this->incrementEndpoint());
            $routes->addRoute('GET', '/count', $this->countEndpoint());
        });
    }

    public function getAllEndpoint(): Callable
    {
        return function () {
            $all = json_encode($this->gCounter->get());
            return new HttpResponse(200, ['Content-Type' => 'application/json'], $all);
        };
    }

    public function incrementEndpoint(): Callable
    {
        return function () {
            $this->gCounter->increment();
            return new HttpResponse(200, ['Content-Type' => 'text/plain'], 'Incremented');
        };
    }

    public function countEndpoint(): Callable
    {
        return function () {
            $count = $this->gCounter->count();
            return new HttpResponse(200, ['Content-Type' => 'text/plain'], (string)$count);
        };
    }

    private function initTimer(array $identifiers): void
    {
        $this->loop->addPeriodicTimer(1, function () use ($identifiers) {
            if (random_int(0, 8) === 0) {
                $idToJoin = $identifiers[random_int(0, count($identifiers) - 1)];
                $this->join($idToJoin);
            }
        });
    }

    private function join($idToJoin): void
    {
        $hostAddress = sprintf('http://127.0.0.1:%s/get', $idToJoin);
        $request = $this->client->request('GET', $hostAddress);
        $request->on('response', function (HttpClientResponse $response) use ($idToJoin) {
            $responseStr = '';
            $response->on('data', function ($chunk) use (&$responseStr) {
                $responseStr .= $chunk;
            });
            $response->on('end', function () use (&$responseStr, $idToJoin) {
                $before = $this->gCounter->get();
                $this->gCounter->join(json_decode($responseStr, true));
                $after = $this->gCounter->get();
                $this->logger->info(
                    sprintf(
                        'Joining %s %s -> %s',
                        $idToJoin,
                        str_replace('"', '', json_encode($before)),
                        str_replace('"', '', json_encode($after))
                    )
                );
            });
        });
        $request->on('error', function ($e) {
            throw $e;
        });
        $request->end();
    }
}
