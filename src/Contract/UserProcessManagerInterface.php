<?php

declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use InvalidArgumentException;
use LogicException;
use SuperKernel\Server\Exception\UserProcessNotFoundException;
use Swoole\Process;
use Swoole\Server;

/**
 * Registers named user workers for attachment by ServerProvider; native Server owns their lifecycle.
 * Callable tasks default to no redirection, a datagram pipe and coroutine execution disabled.
 */
interface UserProcessManagerInterface
{
    /**
     * Registers one worker without creating or starting a native process; returns this manager for chaining.
     * Unnamed workers use their zero-based registration index. Tasks with enabled=false are skipped.
     * Native constructor options are captured in an effective UserProcessInterface without mutating the task.
     * Complete registration before ServerProvider attaches workers; later additions do not change a running Server.
     *
     * @param UserProcessInterface|callable(Process, Server): void $process
     * @param bool|null                                            $enableCoroutine null inherits the task's preference
     *
     * @return self this manager for chaining
     *
     * @throws InvalidArgumentException if the task's pipe type is invalid
     * @throws LogicException           if a name is blank/duplicated or registration exceeds 1024 workers
     */
    public function add(
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine = null,
        ?string                       $name = null,
    ): self;

    /**
     * Registers workerNum entries atomically. Zero is a no-op; disabled tasks consume no names or capacity.
     * A named batch uses name:0, name:1, etc.; a single worker keeps name unchanged.
     * Each entry is attached as an independent native user process when ServerProvider starts its Server.
     *
     * @param UserProcessInterface|callable(Process, Server): void $process
     * @param bool|null                                            $enableCoroutine null inherits the task's preference
     *
     * @return self this manager after all entries have been registered
     *
     * @throws InvalidArgumentException if workerNum is outside 0-1024 or the task's pipe type is invalid
     * @throws LogicException           if a name is blank/duplicated or registration exceeds 1024 workers
     */
    public function addBatch(
        int                           $workerNum,
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine = null,
        ?string                       $name = null,
    ): self;

    /** @return array<array-key, UserProcessInterface> effective tasks in registration order, keyed by expanded name */
    public function all(): array;

    /** @return bool whether the name is registered; this is not a worker-liveness check */
    public function has(string $name): bool;

    /**
     * @return UserProcessInterface the same effective task returned by all() and repeated lookups
     *
     * @throws UserProcessNotFoundException if has($name) is false
     */
    public function get(string $name): UserProcessInterface;
}
