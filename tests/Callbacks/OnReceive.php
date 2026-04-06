<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Callbacks;

use SuperKernel\Server\Contract\Callbacks\OnReceiveInterface;
use Swoole\Server;

final class OnReceive implements OnReceiveInterface
{
	public function __invoke(Server $server, int $fd, int $reactorId, string $data): void
	{
		var_dump(__CLASS__);
	}
}