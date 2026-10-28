<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;
use Swoole\Server\TaskResult;

/**
 * Handles Swoole's optional main Server task-finish callback.
 */
final readonly class OnFinish
{
    /**
     * Receives either Swoole's TaskResult object form or its scalar task-ID and result-data form.
     */
    public function __construct(
        public Server         $server,
        public TaskResult|int $result,
        public mixed          $data = null,
    )
    {
    }
}
