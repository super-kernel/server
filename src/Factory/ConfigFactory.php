<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use SuperKernel\Attribute\Contract;
use SuperKernel\Attribute\Factory;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Server\ServerConfigInterface;

#[
	Contract(ServerConfigInterface::class),
	Factory,
]
final class ConfigFactory
{
	public function __invoke(ConfigInterface $config)
	{
		return $config->get(ServerConfigInterface::class);
	}
}