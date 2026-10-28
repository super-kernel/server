<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server shutdown callback.
 */
final readonly class OnShutdown
{
    /**
     * Receives the main Server after Swoole has shut it down.
     */
    public function __construct(public Server $server)
    {
    }
}
