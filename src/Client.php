<?php

namespace Phergie\Slack\Client\React;

use Evenement\EventEmitter;

class Client extends EventEmitter implements
    ClientInterface
{
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