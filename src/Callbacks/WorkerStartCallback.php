<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnWorkerStart;
use Swoole\Server;

final readonly class WorkerStartCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(
            new OnWorkerStart($server, $workerId),
        );
    }
}
