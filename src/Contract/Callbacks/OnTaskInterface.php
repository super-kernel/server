<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use SuperKernel\Server\Attribute\CallbackEvent;
use SuperKernel\Server\Contract\CallbackEventInterface;
use Swoole\Server;

#[CallbackEvent(event: 'task')]
interface OnTaskInterface extends CallbackEventInterface
{
	public function __invoke(Server $server, int $task_id, int $src_worker_id, mixed $data): void;
}