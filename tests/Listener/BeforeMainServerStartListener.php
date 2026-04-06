<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Listener;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Server\Event\BeforeMainServerStart;
use SuperKernelTest\Server\Callbacks\OnReceive;
use SuperKernelTest\Server\Callbacks\OnRequest;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;

#[Listener]
final readonly class BeforeMainServerStartListener implements ListenerInterface
{
	public function __construct(
		private OnReceive $onReceive,
		private OnRequest $onRequest,
	)
	{
	}

	public function listen(): array
	{
		return [
			BeforeMainServerStart::class,
		];
	}

	/* @psalm-param BeforeMainServerStart $event */
	public function process(object $event): void
	{
		$server = $event->server;

		var_dump(
			[
				__CLASS__,
				$server instanceof HttpServer,
			],
		);

		match (true) {
			$server instanceof HttpServer => $server->on('request', [$this->onRequest, '__invoke']),
			$server instanceof Server     => $server->on('receive', [$this->onReceive, '__invoke']),
			default                       => null,
		};
	}
}