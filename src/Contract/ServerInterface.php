<?php

declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use SuperKernel\Server\Exception\ServerStartException;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Exception as SwooleException;
use Swoole\Server;
use Throwable;

/**
 * Exposes and starts one configured Swoole service without owning its shutdown or signal lifecycle.
 */
interface ServerInterface
{
    /**
     * Returns the server owned by this configured service.
     *
     * @throws ServerStartException When the configured container entry cannot resolve the expected native service
     */
    public function getServer(): Server|CoroutineServer;

    /**
     * Dispatches synchronous pre-start events and enters the configured server runtime.
     * Listener failures propagate unchanged before native startup.
     * Non-coroutine servers reset daemonize=false after these events and before native start.
     * Collected user processes are attached in discovery order after the reset and before native start.
     *
     * @throws ServerStartException When resolution, listener alignment, foreground settings, or startup is rejected
     * @throws SwooleException      When allocating a native user-process pipe fails
     * @throws Throwable            When pre-start listeners, native startup, or startup logging throw
     */
    public function start(): void;
}
