<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/phergie/phergie-irc-parser for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Slack\Client\React
 */

namespace Phergie\Slack\Tests\Client\React;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phake;
use Phergie\Slack\Client\React\Exception;
use React\EventLoop\LoopInterface;
use React\SocketClient\SecureConnector;
use React\Stream\StreamInterface;

/**
 * Tests for \Phergie\Slack\Client\React\Client.
 *
 * @category Phergie
 * @package Phergie\Slack\Client\React
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * Instance of the class under test
	 * @var \Phergie\Slack\Client\React\Client
	 */
	private $client;

	/**
	 * Test Slack message
	 * @var array
	 */
	// Maybe a test?

	/**
	 * Performs common setup used across most tests.
	 */
	public function setUp()
	{
		// Set up a local mock server to listen on a particular port so that 
		// code for establishing a client connection via PHP streams can be
		// tested
		$this->server = stream_socket_server('tcp://0.0.0.0:' . $this->port, $errno, $errstr);
		stream_set_blocking($this->server, 0);
		if (!$this->server) {
			$this->markTestSkipped('Cannot listen on port ' . $this->port);
		}

		// Instantiate the class under test
		$this->client = Phake::partialMock('\Phergie\Slack\Client\React\Client');
	}

	/**
	 * Performs common cleanup used across most tests.
	 */
	public function tearDown()
	{
		// Shut down the mock server connection
		fclose($this->server);
	}

	/**
	 * Tests setLoop().
	 */
	public function testSetLoop()
	{
		$loop = $this->getMockLoop();
		$this->client->setLoop($loop);
		$this->assertSame($loop, $this->client->getLoop());
	}

	/**
	 * Tests getLoop().
	 */
	public function testGetLoop()
	{
		$this->assertInstanceOf('\React\EventLoop\LoopInterface', $this->client->getLoop());
	}

    /**
     * Tests setResolver().
     */
    public function testSetResolver()
    {
        $this->client->setLoop($this->getMockLoop());
        $resolver = $this->getMockResolver();
        $this->client->setResolver($resolver);
        $this->assertSame($resolver, $this->client->getResolver());
    }

    /**
     * Tests getResolver().
     */
    public function testGetResolver()
    {
        $this->client->setLoop($this->getMockLoop());
        $this->assertInstanceOf('\React\Dns\Resolver\Resolver', $this->client->getResolver());
    }

    /**
     * Tests setDnsServer().
     */
    public function testSetDnsServer()
    {
        $ip = '1.2.3.4';
        $this->client->setDnsServer($ip);
        $this->assertSame($ip, $this->client->getDnsServer());
    }

    /**
     * Tests getDnsServer().
     */
    public function testGetDnsServer()
    {
        $this->assertSame('8.8.8.8', $this->client->getDnsServer());
    }

    /**
     * Tests setLogger().
     */
    public function testSetLogger()
    {
        $logger = $this->getMockLogger();
        $this->client->setLogger($logger);
        $this->assertSame($logger, $this->client->getLogger());
    }

    /**
     * Tests getLogger().
     */
    public function testGetLogger()
    {
        $logger = $this->client->getLogger();
        $this->assertInstanceOf('\Psr\Log\LoggerInterface', $logger);
        $this->assertSame($logger, $this->client->getLogger());
    }

    /**
     * Tests setTickInterval().
     */
    public function testSetTickInterval()
    {
        $tickInterval = 0.5;
        $this->client->setTickInterval($tickInterval);
        $this->assertSame($tickInterval, $this->client->getTickInterval());
    }

    /**
     * Tests getTickInterval().
     */
    public function testGetTickInterval()
    {
        $this->assertSame(0.2, $this->client->getTickInterval());
    }

    /**
     * Tests getLogger() as part of code read from STDIN to verify that error
     * logging is properly directed to STDERR by default.
     */
    public function testGetLoggerRunFromStdin()
    {
        $dir = __DIR__;
        $port = $this->port;
        $code = <<<EOF
<?php
require '$dir/../vendor/autoload.php';
\$client = new \Phergie\Slack\Client\React\Client;
\$logger = \$client->getLogger();
\$logger->debug("test");
EOF;
        $script = tempnam(sys_get_temp_dir(), '');
        file_put_contents($script, $code);
        $null = strcasecmp(substr(PHP_OS, 0, 3), 'win') == 0 ? 'NUL' : '/dev/null';
        $php = defined('PHP_BINARY') ? PHP_BINARY : PHP_BINDIR . '/php';

        $command = $php . ' ' . $script . ' 2>' . $null;
        $output = shell_exec($command);
        $this->assertEmpty($output);

        $command = $php . ' ' . $script . ' 2>&1';
        $output = shell_exec($command);
        $this->assertRegExp('/^[0-9]{4}(-[0-9]{2}){2} [0-9]{2}(:[0-9]{2}){2} DEBUG test \\[\\]$/', $output);

        unlink($script);
    }

    /**
     * Tests addConnection() when a socket exception is thrown.
     */
    public function testAddConnectionWithException()
    {
        $connection = $this->getMockConnectionForAddConnection();
        $writeStream = $this->getMockWriteStream();
        Phake::when($this->client)->getWriteStream($connection)->thenReturn($writeStream);
        $logger = $this->getMockLogger();
        $exception = new Exception('message', Exception::ERR_CONNECTION_ATTEMPT_FAILED);

        $this->client->setLogger($logger);
        Phake::when($this->client)
            ->getSocket($this->isType('string'), $this->isType('array'))
            ->thenThrow($exception);

        $this->client->setLoop($this->getMockLoop());
        $this->client->setResolver($this->getMockResolver());
        $this->client->run($connection);

        Phake::inOrder(
            Phake::verify($this->client)->emit('connect.before.each', array($connection)),
            Phake::verify($this->client)->emit('connect.error', array($exception->getMessage(), $connection, $logger)),
            Phake::verify($this->client)->emit('connect.after.each', array($connection, null))
        );

        Phake::verify($logger)->error($exception->getMessage());
    }

    public function testAddConnection()
    {
    	$connection = $this->getMockConnectionForAddConnection();
    	$writeStream = $this->getMockWriteStream();
    	Phake::when()
    }


}
