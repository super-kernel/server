<?php
declare(strict_types=1);

namespace SuperKernel\Server\ServerHandler;

use RuntimeException;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\ServerConfigInterface;
use Swoole\Server as SwooleServer;

final class Server implements ServerInterface
{
	private SwooleServer $server;

	public function __construct(private readonly ServerConfigInterface $serverConfig)
	{
	}

	public function addServer(ServerConfig $config): \Swoole\Coroutine\Http\Server|SwooleServer|\Swoole\Coroutine\Server
	{
		if (!isset($this->server)) {
			$serverName = $config->type->getServer();

			$this->server = new $serverName($config->host, $config->port, $this->serverConfig->getMode()->value, $config->sockType);

			$this->server->set($this->serverConfig->getSettings());

			return $this->server;
		}

		$server = $this->server->listen($config->host, $config->port, $config->sockType);

		if (!$server) {
			throw new RuntimeException("Failed to listen server port [$config->host:$config->port]");
		}

		return $this->server;
	}

	public function start(): void
	{
		$this->server->start();
	}
}