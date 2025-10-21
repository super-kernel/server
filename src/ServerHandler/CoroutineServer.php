<?php
declare(strict_types=1);

namespace SuperKernel\Server\ServerHandler;

use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\ServerConfigInterface;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

final class CoroutineServer implements ServerInterface
{
	private array $servers = [];

	public function __construct(private readonly ServerConfigInterface $serverConfig)
	{
	}

	public function addServer(ServerConfig $config): Coroutine\Http\Server|\Swoole\Server|Coroutine\Server
	{
		$serverName = $config->type->getCoroutineServer();
		$server     = new $serverName($config->host, $config->port);

		$server->set($this->serverConfig->getSettings());

		$this->servers[$config->name] = $server;

		return $server;
	}

	public function start(): void
	{
		run(function () {
			foreach ($this->servers as $server) {
				Coroutine::create(function () use ($server) {
					$server->start();
				});
			}
		});
	}
}