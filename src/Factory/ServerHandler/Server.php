<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory\ServerHandler;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SuperKernel\Server\Config;
use SuperKernel\Server\ConfigInterface;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Interface\ServerInterface;
use Swoole\Server as SwooleServer;

final readonly class Server implements ServerInterface
{
	private SwooleServer $server;

	public function __construct(private ConfigInterface $config, private EventDispatcherInterface $eventDispatcher)
	{
		$serverConfigs = $this->config->getServerConfigs();

		$this->initialization($serverConfigs->first());

		foreach ($serverConfigs->getIterator() as $serverConfig) {
			$this->addServer($serverConfig);
		}
	}

	private function initialization(Config $config): void
	{
		$serverName = $config->type->getServer();
		$mode       = $this->config->getMode();

		$this->server = new $serverName($config->host, $config->port, $mode->value, $config->sock_type);

		$this->eventDispatcher->dispatch(new BeforeServerStart($this->server, $config, $mode));
	}

	public function addServer(Config $config): void
	{
		$server = $this->server->listen($config->host, $config->port, $config->sock_type);

		if (!$server) {
			throw new RuntimeException("Failed to listen server port [$config->host:$config->port]");
		}

		$this->eventDispatcher->dispatch(new BeforeServerStart($this->server, $config, $this->config->getMode()));
	}

	public function start(): void
	{
		$this->server->start();
	}
}