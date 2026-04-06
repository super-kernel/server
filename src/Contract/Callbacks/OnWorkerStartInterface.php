<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnWorkerStartInterface
{
	public function __invoke(Server $server, int $workerId): void;
}