<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnFinishInterface
{
	public function __invoke(Server $server, int $task_id, mixed $data): void;
}