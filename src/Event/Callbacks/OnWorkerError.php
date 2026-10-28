<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;
use Swoole\Server\StatusInfo;

/**
 * Handles Swoole's main Server worker-error callback.
 */
final readonly class OnWorkerError
{
    /**
     * Receives either Swoole's StatusInfo object form or its worker-ID, PID, exit-code, and signal scalar form.
     */
    public function __construct(
        public Server         $server,
        public StatusInfo|int $status,
        public ?int           $workerPid = null,
        public ?int           $exitCode = null,
        public ?int           $signal = null,
    )
    {
    }
}
