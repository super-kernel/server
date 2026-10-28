<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;

/**
 * Handles Swoole's main Server manager-start callback.
 */
final readonly class OnManagerStart
{
    /**
     * Receives the main Server when its manager starts.
     */
    public function __construct(public Server $server)
    {
    }
}
