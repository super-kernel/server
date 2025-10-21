<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use SuperKernel\Server\ServerConfig;
use Swoole\Server;

interface ServerInterface
{
	/**
	 * @param ServerConfig $config
	 *
	 * @return Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server
	 */
	public function addServer(ServerConfig $config): Server|\Swoole\Coroutine\Server|\Swoole\Coroutine\Http\Server;

	public function start(): void;
}