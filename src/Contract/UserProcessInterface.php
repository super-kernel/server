<?php

declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use Swoole\Process;
use Swoole\Server;
use Throwable;

/**
 * Supplies registration eligibility and native options for a worker attached through Server::addProcess().
 *
 * Construction and property reads happen before forking. Keep them free of business resources, reactors and
 * asynchronous work; initialize those resources in execute(). The Server owns startup, reaping and restarting.
 */
interface UserProcessInterface
{
    /** Read in the parent during registration; false excludes this task from the manager. */
    public bool $enabled { get; }

    /** Redirects stdin, stdout and stderr to the process pipe; Swoole then forces a stream pipe. */
    public bool $redirectStdinStdout { get; }

    /**
     * Selects Process::PIPE_TYPE_NONE, Process::PIPE_TYPE_STREAM or Process::PIPE_TYPE_DGRAM.
     * This pipe belongs to the Server's user worker and is not a background-process registry endpoint.
     *
     * @see Process::__construct()
     */
    public int $pipeType { get; }

    /**
     * Requests a native coroutine context for execute(); explicit registration and annotation settings override it.
     * The prepared UserProcessDefinition contains the effective option without changing this property.
     */
    public bool $enableCoroutine { get; }

    /**
     * Initializes child-owned resources and executes work using this native process and its owning Server.
     *
     * Called only in the user worker, including each native Server restart. Returning ends this generation;
     * the Server may restart it. Do not call Process::start(), wait(), daemon() or Server::start() here.
     *
     * @throws Throwable when initialization or execution fails; the native Server handles worker termination
     */
    public function execute(Process $process, Server $server): void;
}
