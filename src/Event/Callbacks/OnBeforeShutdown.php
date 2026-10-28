<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server before-shutdown callback.
 */
final readonly class OnBeforeShutdown
{
    /**
     * Receives the main Server immediately before Swoole begins shutdown.
     */
    public function __construct(public Server $server)
    {
    }
}
