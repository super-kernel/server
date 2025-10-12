<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Attribute\Contract;
use SuperKernel\Attribute\Factory;
use SuperKernel\Server\ServerConfigInterface;
use SuperKernel\Server\Factory\ServerHandler\CoroutineServer;
use SuperKernel\Server\Factory\ServerHandler\Server;
use SuperKernel\Server\Interface\ServerInterface;
use SuperKernel\Server\Mode;

#[
	Contract(ServerInterface::class),
	Factory,
]
final readonly class ServerFactory
{
	public function __construct(private ContainerInterface $container)
	{
	}

	/**
	 * @param ServerConfigInterface $serverConfig
	 *
	 * @return ServerInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(ServerConfigInterface $serverConfig): ServerInterface
	{
		$server = $this->getServer($serverConfig->getMode());

		foreach ($serverConfig->getServerConfigs() as $config) {
			$server->addServer($config);
		}

		return $server;
	}

	/**
	 * @param Mode $mode
	 *
	 * @return ServerInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function getServer(Mode $mode): ServerInterface
	{
		$server = $mode === Mode::SWOOLE_DISABLE
			? $this->container->get(CoroutineServer::class)
			: $this->container->get(Server::class);

		return $server->setMode($mode);
	}
}