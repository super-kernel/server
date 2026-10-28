<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event\Callbacks;

use Swoole\Server;
use Swoole\Server\Task;

/**
 * Handles Swoole's optional main Server task callback.
 */
final class OnTask
{
    /**
     * A listener may set a non-null value to deliver it through Swoole's task-finish behavior.
     */
    public mixed $result = null;

    /**
     * Receives either Swoole's Task object form or its scalar task-ID, source-worker-ID, and data form.
     */
    public function __construct(
        public readonly Server   $server,
        public readonly Task|int $task,
        public readonly ?int     $sourceWorkerId = null,
        public readonly mixed    $data = null,
    )
    {
    }
}
