<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnPipeMessageInterface
{
	public function __invoke(Server $server, int $src_worker_id, mixed $message): void;
}