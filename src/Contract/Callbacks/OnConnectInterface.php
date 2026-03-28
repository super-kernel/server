<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use SuperKernel\Server\Attribute\CallbackEvent;
use SuperKernel\Server\Contract\CallbackEventInterface;
use Swoole\Server;

#[CallbackEvent(event: 'connect')]
interface OnConnectInterface extends CallbackEventInterface
{
	public function __invoke(Server $server, int $fd, int $reactorId): void;
}