<?php
declare(strict_types=1);

namespace SuperKernel\Server\Event;

use SuperKernel\Server\Config;
use Swoole\Server;

final readonly class BeforeMainServerStart
{
	public function __construct(public Server $server, public Config $config)
	{
	}
}