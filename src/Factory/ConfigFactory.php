<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use SuperKernel\Attribute\Contract;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Server\ServerConfigInterface;

#[Contract(ConfigInterface::class)]
final class ConfigFactory
{
	public function __invoke(ConfigInterface $config)
	{
		return $config->get(ServerConfigInterface::class);
	}
}