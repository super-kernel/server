<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server manager-stop callback.
 */
final readonly class OnManagerStop
{
    /**
     * Receives the main Server when its manager stops.
     */
    public function __construct(public Server $server)
    {
    }
}
