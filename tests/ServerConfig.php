<?php
declare(strict_types=1);

namespace SuperKernelTest\Server;

use SuperKernel\Config\Attribute\Configuration;
use SuperKernel\Server\Constants\ServerTypeConstants;
use SuperKernel\Server\Constants\ServerModeConstants;
use SuperKernel\Server\Constants\ServerConstants;
use SuperKernel\Server\Contract\ServerConfigInterface;
use Swoole\Constant;
use function swoole_cpu_num;
use const SWOOLE_HOOK_ALL;
use const SWOOLE_SOCK_TCP;

#[Configuration(ServerConfigInterface::class)]
final readonly class ServerConfig implements ServerConfigInterface
{
	public function getHookFlags(): int
	{
		return SWOOLE_HOOK_ALL;
	}

	public function getType(): ServerConstants
	{
		return ServerConstants::Asynchronous;
	}

	public function getMode(): ServerModeConstants
	{
		return ServerModeConstants::SWOOLE_PROCESS;
	}

	public function getServers(): array
	{
		return [
			[
				'name'      => 'http',
				'type'      => ServerTypeConstants::SERVER_HTTP,
				'host'      => '127.0.0.1',
				'port'      => [
					9901,
				],
				'sock_type' => SWOOLE_SOCK_TCP,
				'options'   => [],
			],
		];
	}

	public function getSettings(): array
	{
		return [
			Constant::OPTION_ENABLE_COROUTINE    => true,
			Constant::OPTION_WORKER_NUM          => swoole_cpu_num(),
			Constant::OPTION_PID_FILE            => '/tmp/super-kernel.pid',
			Constant::OPTION_OPEN_TCP_NODELAY    => true,
			Constant::OPTION_MAX_COROUTINE       => 100000,
			Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
			Constant::OPTION_MAX_REQUEST         => 100000,
			Constant::OPTION_SOCKET_BUFFER_SIZE  => 2 * 1024 * 1024,
			Constant::OPTION_BUFFER_OUTPUT_SIZE  => 2 * 1024 * 1024,
		];
	}
}