<?php
declare(strict_types=1);

namespace SuperKernelTest\Server;

use SuperKernel\Attribute\Contract;
use SuperKernel\Server\ConfigInterface;
use SuperKernel\Server\Config;
use SuperKernel\Server\Mode;
use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\ServerType;
use const SWOOLE_SOCK_TCP;

#[
	Contract(ConfigInterface::class),
]
final class Server implements ConfigInterface
{
	public function getMode(): Mode
	{
		return Mode::SWOOLE_THREAD;
	}

	public function getServerConfigs(): ServerConfig
	{
		return new ServerConfig(
			new Config(
				name     : 'http',
				type     : ServerType::SERVER_HTTP,
				host     : '0.0.0.0',
				port     : 9501,
				sock_type: SWOOLE_SOCK_TCP,
			),
			new Config(
				name     : 'websocket',
				type     : ServerType::SERVER_WEBSOCKET,
				host     : '0.0.0.0',
				port     : 9502,
				sock_type: SWOOLE_SOCK_TCP,
			),
		);
	}
}