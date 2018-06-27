<?php

namespace React\HttpClient;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;
use React\Socket\Connector;

class Client
{
    private $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;
    }

    public function request($method, $url, array $headers = array(), $protocolVersion = '1.0')
    {
        $requestData = new RequestData($method, $url, $headers, $protocolVersion);

        $connector = $this->connector;

        return new Promise(function ($resolve, $reject) use ($requestData, $connector) {
            $resolve(new Request($connector, $requestData));
        });
    }
}
