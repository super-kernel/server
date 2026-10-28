<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnFinish;
use Swoole\Server;
use Swoole\Server\TaskResult;

final readonly class FinishCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server, TaskResult|int $result, mixed $data = null): void
    {
        $this->eventDispatcher->dispatch(
            new OnFinish($server, $result, $data),
        );
    }
}
