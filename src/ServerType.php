<?php
declare(strict_types=1);

namespace SuperKernel\Server;

enum ServerType
{
	case SERVER_TCP;

	case SERVER_UDP;

	case SERVER_HTTP;

	case SERVER_WEBSOCKET;

	public function getServer(): string
	{
		return match ($this) {
			self::SERVER_TCP,
			self::SERVER_UDP       => \Swoole\Server::class,
			self::SERVER_HTTP      => \Swoole\Http\Server::class,
			self::SERVER_WEBSOCKET => \Swoole\WebSocket\Server::class,
		};
	}

	public function getCoroutineServer(): string
	{
		return match ($this) {
			self::SERVER_TCP,
			self::SERVER_UDP       => \Swoole\Coroutine\Server::class,
			self::SERVER_HTTP,
			self::SERVER_WEBSOCKET => \Swoole\Coroutine\Http\Server::class,
		};
	}
}