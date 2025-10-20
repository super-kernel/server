<?php
declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerInterface;
use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Provider;
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
	private ?ServerConfigInterface $serverConfig = null {
		get => $this->serverConfig ??= $this->container->get(ServerConfigInterface::class);
	}

	private ?ServerInterface $serverHandler = null {
		get => $this->serverHandler ??= $this->serverConfig->getMode() === Mode::SWOOLE_DISABLE
			? $this->container->get(CoroutineServer::class)
			: $this->container->get(Server::class);
	}

	public function __construct(private readonly ContainerInterface $container)
	{
		$this->serverHandler->setMode($this->serverConfig->getMode());
	}

	public function __invoke(ServerConfigInterface $serverConfig): ServerInterface
	{
		foreach ($serverConfig->getServers() as $config) {
			$this->serverHandler->addServer($config, $this->serverConfig->getSettings());
		}

		return $this->serverHandler;
	}
}