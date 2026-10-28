<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server start callback.
 */
final readonly class OnStart
{
    /**
     * Receives the started main Server in Swoole's callback context.
     */
    public function __construct(public Server $server)
    {
    }
}
