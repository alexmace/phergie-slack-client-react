<?php
/**
 * @todo Add an interface for the resolver stuff.
 * @todo Add an interface for the logger stuff.
 *
 */

namespace Phergie\Slack\Client\React;

use Evenement\EventEmitter;
use Phergie\Slack\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory;
use React\Dns\Resolver\Resolver;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Devristo\Phpws\Client\WebSocket;

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
    /**
     * Logging stream
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $httpClient;
    protected $webSocketClient;

	/**
     * @var \React\Dns\Resolver\Resolver
     */
    protected $resolver;
    /**
     * @var string
     */
    protected $dnsServer = '8.8.8.8';
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
    /**
     * Sets the DNS Resolver.
     *
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver = null)
    {
        $this->resolver = $resolver;
    }
    /**
     * Get the DNS Resolver, if one isn't set in instance will be created.
     *
     * @return Resolver
     */
    public function getResolver()
    {
        if ($this->resolver instanceof Resolver) {
            return $this->resolver;
        }
        $factory = new Factory();
        $this->resolver = $factory->createCached($this->getDnsServer(), $this->getLoop());
        return $this->resolver;
    }
    /**
     * Set the DNS server to use when looking up IP's
     *
     * @param string $dnsServer
     */
    public function setDnsServer($dnsServer = '8.8.8.8')
    {
        $this->dnsServer = $dnsServer;
    }
    /**
     * Returns the configured DNS server
     *
     * @return string
     */
    public function getDnsServer()
    {
        return $this->dnsServer;
    }

	/**
     * Returns a stream instance for logging data on the socket connection.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            // See testGetLoggerRunFromStdin
            // @codeCoverageIgnoreStart
            $stderr = defined('\STDERR') && !is_null(\STDERR)
                ? \STDERR : fopen('php://stderr', 'w');
            // @codeCoverageIgnoreEnd
            $handler = new StreamHandler($stderr, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter("%datetime% %level_name% %message% %context%\n"));
            $this->logger = new Logger(get_class($this));
            $this->logger->pushHandler($handler);
        }
        return $this->logger;
    }
    /**
     * Sets a logger for logging data on the socket connection.
     *
     * @param \Psr\Log\LoggerInterface
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @todo Need a getWebSocketClient function, but what defaults?
     */

    /**
     * Sets a web socket client.
 	 * 
 	 * @param \Devristo\Phpws\Client\WebSocket
 	 */
    public function setWebSocketClient(WebSocket $webSocketClient) 
    {
    	$this->webSocketClient = $webSocketClient;
    }

    public function getHttpClient()
    {
    	if (!$this->httpClient) {
    		$factory = new \React\HttpClient\Factory();
    		$this->httpClient = $factory->create($this->getLoop(), $this->getResolver());
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

        $loop = $this->getLoop();
        $resolver = $this->getResolver();
        $client = $this;
        $logger = $this->getLogger();

        $request = $this->getHttpClient()->request('GET', 'https://slack.com/api/rtm.start?token=' . $connection->getToken());
        $request->on('response', function($response) use ($loop, $client, $logger) {
        	$response->on('data', function($data) use (&$body) {
        		$body .= $data;
        	});
        	$response->on('end', function() use (&$body, $loop, $client, $logger) {
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