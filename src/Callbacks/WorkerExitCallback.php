<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnWorkerExit;
use Swoole\Server;

final readonly class WorkerExitCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server, int $workerId): void
    {
        $this->eventDispatcher->dispatch(
            new OnWorkerExit($server, $workerId),
        );
    }
}
