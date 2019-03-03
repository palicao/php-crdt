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
    private $gcounter;
    private $client;
    private $logger;

    public function __construct(LoopInterface $loop, GCounterInterface $gcounter, HttpClient $client, LoggerInterface $logger)
    {
        $this->loop = $loop;
        $this->gcounter = $gcounter;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Inits the HTTP Server with 3 endpoints (count, get, increment), and a timer
     * which joins another random server at a random interval
     */
    public function init(int $thisPort, array $ports): void
    {
        $this->initHttpServer($thisPort);
        $this->initTimer(array_values(array_diff($ports, [$thisPort])));
        $this->loop->run();
    }

    private function initHttpServer(int $thisPort): void
    {
        $server = new HttpServer(function (ServerRequestInterface $request) use ($thisPort) {

            $dispatcher = $this->dispatcher();
            $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

            if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
                return new HttpResponse(404, ['Content-Type' => 'text/plain'], 'Not found');
            } else if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
                return new HttpResponse(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
            }

            return $routeInfo[1]($request);
        });

        $socket = new SocketServer($thisPort, $this->loop);
        $server->listen($socket);

        $this->logger->info(sprintf("Server running at http://127.0.0.1:%s", $thisPort));
    }

    private function dispatcher(): Dispatcher
    {
        return simpleDispatcher(function (RouteCollector $routes) {
            $routes->addRoute('GET', '/get', $this->getAllCall());
            $routes->addRoute('POST', '/increment', $this->incrementCall());
            $routes->addRoute('GET', '/count', $this->countCall());
        });
    }

    public function getAllCall(): Callable
    {
        return function (ServerRequestInterface $request) {
            $all = json_encode($this->gcounter->get());
            return new HttpResponse(200, ['Content-Type' => 'application/json'], $all);
        };
    }

    public function incrementCall(): Callable
    {
        return function (ServerRequestInterface $request) {
            $this->gcounter->increment();
            return new HttpResponse(200, ['Content-Type' => 'text/plain'], "Incremented");
        };
    }

    public function countCall(): Callable
    {
        return function (ServerRequestInterface $request) {
            $count = $this->gcounter->count();
            return new HttpResponse(200, ['Content-Type' => 'text/plain'], (string)$count);
        };
    }

    private function initTimer(array $ports): void
    {
        $this->loop->addPeriodicTimer(1, function () use ($ports) {
            if (rand(0, 8) == 0) {
                $portToJoin = $ports[rand(0, count($ports) - 1)];
                $this->join($portToJoin);
            }
        });
    }

    private function join($portToJoin): void
    {
        $hostAddress = sprintf('http://127.0.0.1:%s/get', $portToJoin);
        $request = $this->client->request('GET', $hostAddress);
        $request->on('response', function (HttpClientResponse $response) use ($portToJoin) {
            $responseStr = '';
            $response->on('data', function ($chunk) use (&$responseStr) {
                $responseStr .= $chunk;
            });
            $response->on('end', function () use (&$responseStr, $portToJoin) {
                $before = $this->gcounter->get();
                $this->gcounter->join(json_decode($responseStr, true));
                $after = $this->gcounter->get();
                $this->logger->info(
                    sprintf(
                        "Joining http://127.0.0.1:%s %s -> %s",
                        $portToJoin,
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
