<?php
declare(strict_types=1);

namespace SuperKernel\Server;

use SuperKernel\Server\Contract\Coroutine\Server;
use SuperKernel\Server\Interface\ServerConfigInterface;

final readonly class CoroutineServerConfig implements ServerConfigInterface
{
	public function __construct(
		public string $name,
		public Server $type,
		public string $host,
		public int    $port,
		public array  $options = [],
		public array  $callbacks = [],
	)
	{
	}
}