<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnManagerStart;
use Swoole\Server;

final readonly class ManagerStartCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server): void
    {
        $this->eventDispatcher->dispatch(
            new OnManagerStart($server),
        );
    }
}
