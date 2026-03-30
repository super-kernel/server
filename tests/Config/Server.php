<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Config;

use SuperKernel\Config\Attribute\Configuration;
use SuperKernel\Server\Config;
use SuperKernel\Server\Constants\ServerConstants;
use SuperKernel\Server\Constants\ModeConstants;
use SuperKernel\Server\Constants\TypeConstants;
use SuperKernel\Server\Contract\ServerInterface;
use Swoole\Constant;
use function swoole_cpu_num;
use const SWOOLE_HOOK_ALL;
use const SWOOLE_SOCK_TCP;

#[Configuration(ServerInterface::class)]
final readonly class Server implements ServerInterface
{
	public function getMode(): ModeConstants
	{
		return ModeConstants::SWOOLE_PROCESS;
	}

	public function getType(): ServerConstants
	{
		return ServerConstants::Asynchronous;
	}

	public function getHookFlags(): int
	{
		return SWOOLE_HOOK_ALL;
	}

	public function getServers(): iterable
	{
		yield new Config(
			name    : 'http',
			type    : TypeConstants::SERVER_HTTP,
			host    : '127.0.0.1',
			port    : 9901,
			sockType: SWOOLE_SOCK_TCP,
			settings: [],
		);
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