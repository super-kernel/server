<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server worker-start callback.
 */
final readonly class OnWorkerStart
{
    /**
     * Receives the main Server and the started worker identifier.
     */
    public function __construct(public Server $server, public int $workerId)
    {
    }
}
