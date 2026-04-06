<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnTaskInterface
{
	public function __invoke(Server $server, int $task_id, int $src_worker_id, mixed $data): void;
}