<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server before-reload callback.
 */
final readonly class OnBeforeReload
{
    /**
     * Receives the main Server before Swoole reloads its workers.
     */
    public function __construct(public Server $server)
    {
    }
}
