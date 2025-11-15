<?php
declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Di\Attribute\Factory;
use SuperKernel\Di\Attribute\Provider;
use SuperKernel\Server\ServerConfigInterface;

#[
	Provider(ServerConfigInterface::class),
	Factory,
]
final class ServerConfigProvider
{
	public function __invoke(ConfigInterface $config)
	{
		return $config->get(ServerConfigInterface::class);
	}
}