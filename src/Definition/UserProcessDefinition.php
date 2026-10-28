<?php

declare(strict_types=1);

namespace SuperKernel\Server\Definition;

use Closure;
use SuperKernel\Server\Contract\UserProcessInterface;
use Swoole\Process;
use Swoole\Server;

/** Adapts a user-worker callback with the native options captured during registration. */
final readonly class UserProcessDefinition implements UserProcessInterface
{
    /** @param Closure(Process, Server): void $callback */
    public function __construct(
        private Closure $callback,
        public bool     $redirectStdinStdout = false,
        public int      $pipeType = Process::PIPE_TYPE_DGRAM,
        public bool     $enableCoroutine = false,
        public bool     $enabled = true,
    )
    {
    }

    public function execute(Process $process, Server $server): void
    {
        ($this->callback)($process, $server);
    }
}
