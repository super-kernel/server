<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnWorkerStop;
use Swoole\Server;

final readonly class WorkerStopCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(
            new OnWorkerStop($server, $workerId),
        );
    }
}
