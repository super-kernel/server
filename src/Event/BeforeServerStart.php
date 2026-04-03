<?php
declare(strict_types=1);

namespace SuperKernel\Server\Event;

use Swoole\Server;
use Swoole\Server\Port;

final readonly class BeforeServerStart
{
	public function __construct(public string $name, public Port|Server $server)
	{
	}
}