<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event;

use SuperKernel\Contract\EventInterface;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;

/**
 * Publishes the selected logical main service immediately before listener bootstrap.
 */
final readonly class AfterMainServerCreated implements EventInterface
{
    public function __construct(private Server|CoroutineServer $server)
    {
    }

    public function server(): Server|CoroutineServer
    {
        return $this->server;
    }
}
