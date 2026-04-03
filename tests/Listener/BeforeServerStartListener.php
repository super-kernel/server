<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Listener;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Server\Event\BeforeServerStart;
use Swoole\Server;

#[Listener]
final class BeforeServerStartListener implements ListenerInterface
{
	public function listen(): array
	{
		return [
			BeforeServerStart::class,
		];
	}

	/* @psalm-param BeforeServerStart $event */
	public function process(object $event): void
	{
		if ($event->server instanceof Server) {
		}
	}
}