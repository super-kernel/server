<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use SuperKernel\Server\Attribute\CallbackEvent;
use SuperKernel\Server\Contract\CallbackEventInterface;
use Swoole\Server;

#[CallbackEvent(event: 'workerError')]
interface OnWorkerErrorInterface extends CallbackEventInterface
{
	public function __invoke(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal): void;
}