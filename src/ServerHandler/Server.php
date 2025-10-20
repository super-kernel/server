<?php
declare(strict_types=1);

namespace SuperKernel\Server\ServerHandler;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Mode;
use SuperKernel\Server\ServerConfig;
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

	private function initialization(ServerConfig $config, array $settings): void
	{
		$serverName = $config->type->getServer();

		$this->server = new $serverName($config->host, $config->port, $this->mode->value, $config->sockType);

		$this->server->set($settings);

		$this->eventDispatcher->dispatch(new BeforeServerStart($this->server, $config, $this->mode));
	}

	public function addServer(ServerConfig $config, array $settings): void
	{
		if (!isset($this->server)) {
			$this->initialization($config, $settings);
			return;
		}

		$server = $this->server->listen($config->host, $config->port, $config->sockType);

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