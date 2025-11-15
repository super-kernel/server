<?php
declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerInterface;
use SuperKernel\Di\Attribute\Factory;
use SuperKernel\Di\Attribute\Provider;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Mode;
use SuperKernel\Server\ServerConfigInterface;
use SuperKernel\Server\ServerHandler\CoroutineServer;
use SuperKernel\Server\ServerHandler\Server;

#[
	Provider(ServerInterface::class),
	Factory,
]
final class ServerProvider
{
	private ?Server $server = null {
		get => $this->server ??= $this->container->get(Server::class);
	}

	private ?CoroutineServer $coroutineServer = null {
		get => $this->coroutineServer ??= $this->container->get(CoroutineServer::class);
	}

	public function __construct(private readonly ContainerInterface $container)
	{
	}

	public function __invoke(ServerConfigInterface $serverConfig): ServerInterface
	{
		return $serverConfig->getMode() === Mode::SWOOLE_DISABLE
			? $this->coroutineServer
			: $this->server;
	}
}