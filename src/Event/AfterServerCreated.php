<?php

declare(strict_types=1);

namespace SuperKernel\Server\Event;

use SuperKernel\Contract\EventInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;

/**
 * Publishes one configured Swoole listener immediately before native startup.
 *
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 */
final readonly class AfterServerCreated implements EventInterface
{
    /**
     * @psalm-param ListenerConfiguration $configuration
     */
    public function __construct(private Server|ServerPort|CoroutineServer $server, private array $configuration)
    {
    }

    public function server(): Server|ServerPort|CoroutineServer
    {
        return $this->server;
    }

    /**
     * @return ListenerConfiguration
     */
    public function configuration(): array
    {
        return $this->configuration;
    }
}
