<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnWorkerErrorInterface
{
	public function __invoke(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void;
}