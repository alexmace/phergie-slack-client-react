<?php

namespace Phergie\Slack\Client\React;

use Evenement\EventEmitter;
use Phergie\Slack\ConnectionInterface;
use React\EventLoop\LoopInterface;

class Client extends EventEmitter implements
    ClientInterface,
    LoopAccessorInterface,
    LoopAwareInterface
{
	/**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    protected $httpClient;
    protected $webSocketClient;

    /**
     * Sets the event loop dependency.
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
    }
    /**
     * Returns the event loop dependency, initializing it if needed.
     *
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        if (!$this->loop) {
            $this->loop = \React\EventLoop\Factory::create();
        }
        return $this->loop;
    }

    public function getHttpClient()
    {
    	if (!$this->httpClient) {
    		$this->httpClient = \React\HttpClient\Factory();
    	}
    	return $this->httpClient;
    }

    /**
     * Initializes an IRC connection.
     *
     * Emits connect.before.each and connect.after.each events before and
     * after connection attempts are established, respectively.
     *
     * Emits a connect.error event if a connection attempt fails.
     *
     * @param \Phergie\Irc\ConnectionInterface $connection Metadata for connection to establish
     * @throws \Phergie\Irc\Client\React\Exception if unable to establish the connection
     */
    public function addConnection(ConnectionInterface $connection)
    {
        $this->emit('connect.before.each', array($connection));

        $request = $this->getHttpClient()->request('GET', 'https://slack.com/api/rtm.start?token=' . $connection->getToken());
        $request->on('response', function($response) use ($loop, $dns, $client, $logger) {
        	$response->on('data', function($data) use (&$body) {
        		$body .= $data;
        	});
        	$response->on('end', function() use (&$body, $loop, $dns, $client, $logger) {
        		$slackDetails = json_decode($body);

        		$client->setWebSocketClient(new \Devristo\Phpws\Client\WebSocket($slackDetails->url, $loop, $logger));
        	});
        });
        $request->end();
/*
		Probably won't want to distinguish between these for Slack.

        if ($this->getTransport($connection) === 'ssl') {
            $this->addSecureConnection($connection);
        } else {
            $this->addUnsecuredConnection($connection);
        }
*/
    }
    /**
     * Executes the event loop, which continues running until no active
     * connections remain.
     *
     * @param \Phergie\Irc\ConnectionInterface|\Phergie\Irc\ConnectionInterface[] $connections
     */
    public function run($connections)
    {
        if (!is_array($connections)) {
            $connections = array($connections);
        }
        $this->on('connect.error', function($message, $connection, $logger) {
            $logger->error($message);
        });
        $this->emit('connect.before.all', array($connections));
        foreach ($connections as $connection) {
            $this->addConnection($connection);
        }
        $writes = array_map(
            function($connection) {
                return $connection->getOption('write');
            },
            $connections
        );
        $this->emit('connect.after.all', array($connections, $writes));
        $this->getLoop()->run();
    }
}