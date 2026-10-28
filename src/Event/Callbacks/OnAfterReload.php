<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server after-reload callback.
 */
final readonly class OnAfterReload
{
    /**
     * Receives the main Server after Swoole reloads its workers.
     */
    public function __construct(public Server $server)
    {
    }
}
