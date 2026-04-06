<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnReceiveInterface
{
	public function __invoke(Server $server, int $fd, int $reactorId, string $data): void;
}