<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/alexmace/phergie-slack-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Irc\Client\React
 */
namespace Phergie\Slack\Client\React;
use Evenement\EventEmitterInterface;
use Phergie\Slack\ConnectionInterface;
use Psr\Log\LoggerInterface;
/**
 * Interface for an Slack client implementation.
 *
 * @category Phergie
 * @package Phergie\Slack\Client\React
 */
interface ClientInterface extends EventEmitterInterface
{
    /**
     * Initializes an Slack connection.
     *
     * @param \Phergie\Slack\ConnectionInterface $connection Metadata for connection to establish
     * @throws \Phergie\Slack\Client\React\Exception if unable to establish the connection
     */
    public function addConnection(ConnectionInterface $connection);
    /**
     * Executes the event loop, which continues running until no active
     * connections remain.
     *
     * @param \Phergie\Slack\ConnectionInterface|\Phergie\Slack\ConnectionInterface[] $connections
     */
    public function run($connections);
}