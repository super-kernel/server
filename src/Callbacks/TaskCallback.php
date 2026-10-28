<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnTask;
use Swoole\Server;
use Swoole\Server\Task;

final readonly class TaskCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(
        Server   $server,
        Task|int $task,
        ?int     $sourceWorkerId = null,
        mixed    $data = null,
    ): mixed
    {
        $event = new OnTask($server, $task, $sourceWorkerId, $data);

        $this->eventDispatcher->dispatch($event);

        return $event->result;
    }
}
