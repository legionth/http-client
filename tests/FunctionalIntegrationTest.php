<?php

namespace React\Tests\HttpClient;

use Clue\React\Block;
use React\EventLoop\Factory;
use React\HttpClient\Client;
use React\HttpClient\Response;
use React\Promise\Deferred;
use React\Promise\Stream;
use React\Socket\Server;
use React\Socket\ConnectionInterface;

class FunctionalIntegrationTest extends TestCase
{
    /**
     * Test timeout to use for local tests.
     *
     * In practice this would be near 0.001s, but let's leave some time in case
     * the local system is currently busy.
     *
     * @var float
     */
    const TIMEOUT_LOCAL = 1.0;

    /**
     * Test timeout to use for remote (internet) tests.
     *
     * In pratice this should be below 1s, but this relies on infrastructure
     * outside our control, so consider this a maximum to avoid running for hours.
     *
     * @var float
     */
    const TIMEOUT_REMOTE = 10.0;

    public function testRequestToLocalhostEmitsSingleRemoteConnection()
    {
        $loop = Factory::create();

        $server = new Server(0, $loop);
        $server->on('connection', $this->expectCallableOnce());
        $server->on('connection', function (ConnectionInterface $conn) use ($server) {
            $conn->end("HTTP/1.1 200 OK\r\n\r\nOk");
            $server->close();
        });
        $port = parse_url($server->getAddress(), PHP_URL_PORT);

        $client = new Client($loop);
        $promise = $client->request('GET', 'http://localhost:' . $port);

        $promise->then(function ($request) use ($loop) {
            $promise = Stream\first($request, 'close');
            $request->end();

            Block\await($promise, $loop, FunctionalIntegrationTest::TIMEOUT_LOCAL);
        });
    }

    public function testRequestLegacyHttpServerWithOnlyLineFeedReturnsSuccessfulResponse()
    {
        $loop = Factory::create();

        $server = new Server(0, $loop);
        $server->on('connection', function (ConnectionInterface $conn) use ($server) {
            $conn->end("HTTP/1.0 200 OK\n\nbody");
            $server->close();
        });

        $client = new Client($loop);
        $promise = $client->request('GET', str_replace('tcp:', 'http:', $server->getAddress()));

        $that = $this;
        $promise->then(function ($request) use ($loop, $that) {
            $once = $that->expectCallableOnceWith('body');
            $request->on('response', function (Response $response) use ($once) {
                $response->on('data', $once);
            });

            $promise = Stream\first($request, 'close');
            $request->end();

            Block\await($promise, $loop, FunctionalIntegrationTest::TIMEOUT_LOCAL);
        });
    }

    /** @group internet */
    public function testSuccessfulResponseEmitsEnd()
    {
        $loop = Factory::create();
        $client = new Client($loop);

        $promise = $client->request('GET', 'http://www.google.com/');

        $that = $this;
        $promise->then(function ($request) use ($that, $loop) {
            $once = $that->expectCallableOnce();

            $request->on('response', function (Response $response) use ($once) {
                $response->on('end', $once);
            });

            $promise = Stream\first($request, 'close');
            $request->end();

            Block\await($promise, $loop, FunctionalIntegrationTest::TIMEOUT_REMOTE);
        });


    }

    /** @group internet */
    public function testPostDataReturnsData()
    {
        $loop = Factory::create();
        $client = new Client($loop);

        $data = str_repeat('.', 33000);
        $promise = $client->request('POST', 'https://' . (mt_rand(0, 1) === 0 ? 'eu.' : '') . 'httpbin.org/post', array('Content-Length' => strlen($data)));

        $that = $this;
        $promise->then(function ($request) use ($that, $loop) {
            $deferred = new Deferred();
            $request->on('response', function (Response $response) use ($deferred) {
                $deferred->resolve(Stream\buffer($response));
            });

            $request->on('error', 'printf');
            $request->on('error', $that->expectCallableNever());

            $request->end($data);

            $buffer = Block\await($deferred->promise(), $loop, FunctionalIntegrationTest::TIMEOUT_REMOTE);

            $that->assertNotEquals('', $buffer);

            $parsed = json_decode($buffer, true);
            $that->assertTrue(is_array($parsed) && isset($parsed['data']));
            $that->assertEquals(strlen($data), strlen($parsed['data']));
            $that->assertEquals($data, $parsed['data']);
        });
    }

    /** @group internet */
    public function testPostJsonReturnsData()
    {
        $loop = Factory::create();
        $client = new Client($loop);

        $data = json_encode(array('numbers' => range(1, 50)));
        $promise = $client->request('POST', 'https://httpbin.org/post', array('Content-Length' => strlen($data), 'Content-Type' => 'application/json'));

        $that = $this;
        $promise->then(function ($request) use ($that, $loop) {
            $deferred = new Deferred();
            $request->on('response', function (Response $response) use ($deferred) {
                $deferred->resolve(Stream\buffer($response));
            });

            $request->on('error', 'printf');
            $request->on('error', $that->expectCallableNever());

            $request->end($data);

            $buffer = Block\await($deferred->promise(), $loop, FunctionalIntegrationTest::TIMEOUT_REMOTE);

            $that->assertNotEquals('', $buffer);

            $parsed = json_decode($buffer, true);
            $that->assertTrue(is_array($parsed) && isset($parsed['json']));
            $that->assertEquals(json_decode($data, true), $parsed['json']);
        });
    }

    /** @group internet */
    public function testCancelPendingConnectionEmitsClose()
    {
        $loop = Factory::create();
        $client = new Client($loop);

        $that = $this;
        $promise = $client->request('GET', 'http://www.google.com/');
        $promise->then(function ($request) use ($that) {
            $request->on('error', $that->expectCallableNever());
            $request->on('close', $that->expectCallableOnce());
            $request->end();
            $request->close();
        });
    }
}
