<?php

declare(strict_types=1);

namespace SuperKernel\Server\Callbacks;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Event\Callbacks\OnPipeMessage;
use Swoole\Server;
use Swoole\Server\PipeMessage;

final readonly class PipeMessageCallback
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(Server $server, PipeMessage|int $message, mixed $data = null): void
    {
        $this->eventDispatcher->dispatch(
            new OnPipeMessage($server, $message, $data),
        );
    }
}
