<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server worker-exit callback.
 */
final readonly class OnWorkerExit
{
    /**
     * Receives the main Server and the exiting worker identifier.
     */
    public function __construct(public Server $server, public int $workerId)
    {
    }
}
