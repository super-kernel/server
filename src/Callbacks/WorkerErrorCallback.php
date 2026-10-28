<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnWorkerError;
use Swoole\Server;
use Swoole\Server\StatusInfo;

final readonly class WorkerErrorCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(
        Server         $server,
        StatusInfo|int $status,
        ?int           $workerPid = null,
        ?int           $exitCode = null,
        ?int           $signal = null,
    ): void
    {
        $this->eventDispatcher->dispatch(
            new OnWorkerError($server, $status, $workerPid, $exitCode, $signal),
        );
    }
}
