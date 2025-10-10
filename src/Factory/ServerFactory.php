<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Attribute\Contract;
use SuperKernel\Attribute\Factory;
use SuperKernel\Server\ConfigInterface;
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
	 * @param ConfigInterface $config
	 *
	 * @return ServerInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(ConfigInterface $config): ServerInterface
	{
		return $config->getMode() === Mode::SWOOLE_DISABLE
			? $this->container->get(CoroutineServer::class)
			: $this->container->get(Server::class);
	}
}