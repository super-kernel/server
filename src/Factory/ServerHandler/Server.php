<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory\ServerHandler;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\Mode;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Interface\ServerInterface;
use Swoole\Server as SwooleServer;

final class Server implements ServerInterface
{
	private SwooleServer $server;

	private Mode $mode;

	public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
	{
	}

	public function setMode(Mode $mode): ServerInterface
	{
		$this->mode = $mode;

		return $this;
	}

	private function initialization(ServerConfig $config): void
	{
		$serverName = $config->type->getServer();

		$this->server = new $serverName($config->host, $config->port, $this->mode->value, $config->sock_type);

		$this->eventDispatcher->dispatch(new BeforeServerStart($this->server, $config, $this->mode));
	}

	public function addServer(ServerConfig $config): void
	{
		if (!isset($this->server)) {
			$this->initialization($config);
			return;
		}

		$server = $this->server->listen($config->host, $config->port, $config->sock_type);

		if (!$server) {
			throw new RuntimeException("Failed to listen server port [$config->host:$config->port]");
		}

		$this->eventDispatcher->dispatch(new BeforeServerStart($this->server, $config, $this->mode));
	}

	public function start(): void
	{
		$this->server->start();
	}
}