<?php

use React\HttpClient\Client;
use React\HttpClient\Request;
use React\HttpClient\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new Client($loop);

$promise = $client->request('GET', isset($argv[1]) ? $argv[1] : 'https://google.com/');

$promise->then(function (Request $request) {
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
