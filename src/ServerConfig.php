<?php
declare(strict_types=1);

namespace SuperKernel\Server;

use IteratorAggregate;
use RuntimeException;
use Traversable;

final class ServerConfig implements IteratorAggregate
{
	private array $configs;

	public function __construct(Config ...$configs)
	{
		$this->configs = $configs;
	}

	public function first(): Config
	{
		if (empty($this->configs)) {
			throw new RuntimeException('No Config available.');
		}

		return array_shift($this->configs);
	}

	/**
	 * @return Traversable
	 * @throws void
	 */
	public function getIterator(): Traversable
	{
		foreach ($this->configs as $config) {
			yield $config;
		}
	}
}