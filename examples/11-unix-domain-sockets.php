<?php

use React\HttpClient\Client;
use React\HttpClient\Request;
use React\HttpClient\Response;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$connector = new FixedUriConnector(
    'unix:///var/run/docker.sock',
    new UnixConnector($loop)
);

$client = new Client($loop, $connector);

$promise = $client->request('GET', 'http://localhost/info');

$promise->then(function(Request $request) {
	$request->on('response', function (Response $response) {
		var_dump($response->getHeaders());

		$response->on('data', function ($chunk) {
			echo $chunk;
		});

		$response->on('end', function () {
			echo 'DONE' . PHP_EOL;
		});
	});

	$request->on('error', function (\Exception $e) {
		echo $e;
	});

	$request->end();

});



$loop->run();
