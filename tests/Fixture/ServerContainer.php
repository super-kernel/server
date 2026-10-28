<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;

use function sprintf;

final readonly class ServerContainer implements ContainerInterface
{
    public function __construct(private Server|CoroutineServer $server, private ?ContainerInterface    $fallback = null)
    {
    }

    public function get(string $id): mixed
    {
        if (Server::class === $id && $this->server instanceof Server) {
            return $this->server;
        }

        if (CoroutineServer::class === $id && $this->server instanceof CoroutineServer) {
            return $this->server;
        }

        if ($this->fallback instanceof ContainerInterface) {
            return $this->fallback->get($id);
        }

        throw new RuntimeException(sprintf('Container entry %s is not available.', $id));
    }

    public function has(string $id): bool
    {
        if (Server::class === $id && $this->server instanceof Server) {
            return true;
        }

        if (CoroutineServer::class === $id && $this->server instanceof CoroutineServer) {
            return true;
        }

        return $this->fallback?->has($id) ?? false;
    }
}
