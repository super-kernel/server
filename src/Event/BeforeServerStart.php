<?php
declare(strict_types=1);

namespace SuperKernel\Server\Event;

use Swoole\Coroutine\Server;

final class BeforeServerStart
{
	public function __construct(public Server $server, public ConfigInterface $config)
	{
	}
}