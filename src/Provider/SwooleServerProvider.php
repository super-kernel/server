<?php
declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Provider;
use SuperKernel\Server\Contract\ServerInterface;
use Swoole\Server;

#[
	Provider(Server::class),
	Factory,
]
final class SwooleServerProvider
{
	/**
	 * @param ContainerInterface $container
	 *
	 * @return Server
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(ContainerInterface $container): Server
	{
		return $container->get(ServerInterface::class)->getServer();
	}
}