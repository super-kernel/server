<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;
use Swoole\Server\PipeMessage;

/**
 * Handles Swoole's optional main Server pipe-message callback.
 */
final readonly class OnPipeMessage
{
    /**
     * Receives either Swoole's PipeMessage object form or its scalar source-worker-ID and message-data form.
     */
    public function __construct(
        public Server          $server,
        public PipeMessage|int $message,
        public mixed           $data = null,
    )
    {
    }
}
