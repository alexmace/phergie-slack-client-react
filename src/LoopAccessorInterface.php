<?php
/**
 * Phergie (http://phergie.org)
 *
 * @link http://github.com/alexmace/phergie-slack-client-react for the canonical source repository
 * @copyright Copyright (c) 2008-2014 Phergie Development Team (http://phergie.org)
 * @license http://phergie.org/license New BSD License
 * @package Phergie\Slack\Client\React
 */
namespace Phergie\Slack\Client\React;
/**
 * Interface for accesing an event loop.
 *
 * @category Phergie
 * @package Phergie\Slack\Client\React
 */
interface LoopAccessorInterface
{
    /**
     * Returns the event loop in use by the implementing class.
     *
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop();
}