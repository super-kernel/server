<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server worker-stop callback.
 */
final readonly class OnWorkerStop
{
    /**
     * Receives the main Server and the stopped worker identifier.
     */
    public function __construct(public Server $server, public int $workerId)
    {
    }
}
