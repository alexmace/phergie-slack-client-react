# phergie/phergie-slack-client-react

A bare-bones PHP-based Slack Real Time Messaging API client library built on React.

[![Build Status](https://secure.travis-ci.org/alexmace/phergie-slack-client-react.png?branch=master)](http://travis-ci.org/alexmace/phergie-alexmace-client-react)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "minimum-stability": "dev",
    "require": {
        "phergie/phergie-slack-client-react": "dev-master"
    }
}
```

If you plan to use SSL-enabled connections, you will also need to install [react/socket-client](https://github.com/reactphp/socket-client).

```JSON
{
    "require": {
         "react/socket-client": "0.3.*"
    }
}
```

## Design goals

* Minimalistic and extensible design
* Informative logging of client-server interactions
* Simple easy-to-understand API

## Usage

This example makes the bot greet other users as they join any of the channels in which the bot is present.

```php
<?php
$connection = new \Phergie\Slack\Connection();
// ...

$client = new \Phergie\Slack\Client\React\Client();
$client->on('slack.received', function($message, $write, $connection, $logger) {
    if ($message['command'] !== 'JOIN') {
        return;
    }
    $channel = $message['params']['channels'];
    $nick = $message['nick'];
    $write->ircPrivmsg($channel, 'Welcome ' . $nick . '!');
});
$client->run($connection);

// Also works:
// $client->run(array($connection1, ..., $connectionN));
```

1. Create and configure an instance of the connection class, `\Phergie\Slack\Connection`, for each server the bot will connect to. See [phergie-slack-connection documentation](https://github.com/alexmace/phergie-slack-connection#usage) for more information on configuring connection objects.
2. Create an instance of the client class, `\Phergie\Slack\Client\React\Client`.
3. Call the client object's `on()` method any number of times, each time specifying an event to monitor and a callback that will be executed whenever that event is received from the server.
4. Call the client object's `run()` method with a connection object or array of multiple connection objects created in step #1.

## Client Events

Below are the events supported by the client. Its `on()` method can be used to add callbacks for them.

### connect.before.all

Emitted before any connections are established.

#### Parameters

* `\Phergie\Slack\ConnectionInterface[] $connections` - array of all connection objects

#### Example

```php
<?php
$client->on('connect.before.all', function(array $connections) {
    // ...
});
```

### connect.after.all

Emitted after all connections are established.

#### Parameters

* `\Phergie\Slack\ConnectionInterface[] $connections` - array of all connection objects
* `\Phergie\Slack\Client\React\WriteStream[] $writes` - corresponding array of connection write streams

Note that if a connection attempt failed, the value in `$writes` for that connection will be `null`.

#### Example

```php
<?php
$client->on('connect.after.all', function(array $connections, array $writes) {
    // ...
});
```

### connect.before.each

Emitted before each connection is established.

#### Parameters

* `\Phergie\Slack\ConnectionInterface $connection` - object for the connection to be established

#### Example

```php
<?php
$client->on('connect.before.each', function(\Phergie\Slack\ConnectionInterface $connection) {
    // ...
});
```

### connect.after.each

Emitted after each connection is established.

One potentially useful application of this is to institute a delay between connections in cases where the client is attempting to establish multiple connections to the same server and that server throttles connection attempts by origin to prevent abuse, DDoS attacks, etc.

#### Parameters

* `\Phergie\Slack\ConnectionInterface $connection` - object for the established connection
* `\Phergie\Slack\Client\React\WriteStream|null $write` - write stream for the connection

Note that if the connection attempt failed, `$write` will be `null`.

#### Example

```php
<?php
$client->on('connect.after.each', function(\Phergie\Slack\ConnectionInterface $connection, \Phergie\Slack\Client\React\WriteStream $write) {
    // ...
});
```

### connect.error

Emitted when an error is encountered on a connection.

#### Parameters

* `Exception $exception ` - exception describing the error that encountered
* `\Phergie\Slack\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Slack\ConnectionInterface` (see [its source code](https://github.com/alexmace/phergie-slack-connection/blob/master/src/Phergie/Slack/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('connect.error', function(
    \Exception $message,
    \Phergie\Slack\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) use ($client) {
    $logger->debug('Connection to ' . $connection->getServerHostname() . ' lost: ' . $e->getMessage());
});
```

### connect.end

Emitted when a connection is terminated.

This can be useful for re-establishing a connection if it is unexpectedly terminated.

#### Parameters

* `\Phergie\Slack\ConnectionInterface $connection` - container that stores metadata for the connection that was terminated and implements the interface `\Phergie\Slack\ConnectionInterface` (see [its source code](https://github.com/alexmace/phergie-slack-connection/blob/master/src/Phergie/Slack/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('connect.end', function(\Phergie\Slack\ConnectionInterface $connection, \Psr\Log\LoggerInterface $logger) use ($client) {
    $logger->debug('Connection, attempting to reconnect');
    $client->addConnection($connection);
});
```

### slack.received

Emitted when an Slack event is received from the server.

#### Parameters

* `array $message` - associative array containing data for the event received from the server as obtained by `\Phergie\Slack\Parser` (see [its documentation](https://github.com/alexmace/phergie-slack-parser#usage) for examples)
* `\Phergie\Slack\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Slack\GeneratorInterface` (see [its source code](https://github.com/alexmace/phergie-slack-generator/blob/master/src/Phergie/Slack/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Slack\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Slack\ConnectionInterface` (see [its source code](https://github.com/alexmace/phergie-slack-connection/blob/master/src/Phergie/Slack/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('slack.received', function(
    array $message,
    \Phergie\Slack\Client\React\WriteStream $write,
    \Phergie\Slack\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

### slack.sent

Emitted when an Slack event is sent by the client to the server.

#### Parameters

* `string $message` - message being sent by the client
* `\Phergie\Slack\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Slack\GeneratorInterface` (see [its source code](https://github.com/alexmace/phergie-slack-generator/blob/master/src/Phergie/Slack/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Slack\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Slack\ConnectionInterface` (see [its source code](https://github.com/alexmace/phergie-slack-connection/blob/master/src/Phergie/Slack/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('slack.sent', function(
    $message,
    \Phergie\Slack\Client\React\WriteStream $write,
    \Phergie\Slack\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

### slack.tick

Emitted periodically on each connection to allow events to be sent
asynchronously versus in response to received or sent events. The interval
between invocations is specified in seconds and set using the client's
`setTickInterval()` method.

#### Parameters

* `\Phergie\Slack\Client\React\WriteStream $write` - stream that will send new events from the client to the server when its methods are called and implements the interface `\Phergie\Slack\GeneratorInterface` (see [its source code](https://github.com/alexmace/phergie-slack-generator/blob/master/src/Phergie/Slack/GeneratorInterface.php) for a list of available methods)
* `\Phergie\Slack\ConnectionInterface $connection` - container that stores metadata for the connection on which the event occurred and implements the interface `\Phergie\Slack\ConnectionInterface` (see [its source code](https://github.com/alexmace/phergie-slack-connection/blob/master/src/Phergie/Slack/ConnectionInterface.php) for a list of available methods)
* `\Psr\Log\LoggerInterface $logger` - logger for logging any relevant events from the listener which go to [stdout](http://en.wikipedia.org/wiki/Standard_streams#Standard_output_.28stdout.29) by default (see [the Monolog documentation](https://github.com/Seldaek/monolog#monolog---logging-for-php-53-) for more information)

#### Example

```php
<?php
$client->on('slack.tick', function(
    \Phergie\Slack\Client\React\WriteStream $write,
    \Phergie\Slack\ConnectionInterface $connection,
    \Psr\Log\LoggerInterface $logger
) {
    // ...
});
```

## Timers

In some cases, it's desirable to execute a callback on a specified interval rather than in response to a specific event.

### One-Time Callbacks

To add one-time callbacks that execute after a specified amount of time (in seconds):

```php
<?php
$client->addTimer(5, function() {
    // ...
});
```

The above example will execute the specified callback at least 5 seconds after it's added.

### Recurring Callbacks

To add recurring callbacks that execute on a specified interval (in seconds):

```php
<?php
$client->addPeriodicTimer(5, function() {
    // ...
});
```

The above example will execute the specified callback at least every 5 seconds after it's added.

## Connection Options

### force-ip4

Connection sockets will use IPv6 by default where available. If you need to force usage of IPv4, set this option to `true`.

```php
<?php
$connection->setOption('force-ipv4', true);
```

### transport

By default, a standard TCP socket is used. For IRC servers that support TLS or SSL, specify an [appropriate transport](http://www.php.net/manual/en/transports.inet.php).

```php
<?php
$connection->setOption('transport', 'ssl');
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.

## Community

Check out #phergie on irc.freenode.net.

