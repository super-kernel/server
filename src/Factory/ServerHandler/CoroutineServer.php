<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory\ServerHandler;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\Config;
use SuperKernel\Server\ConfigInterface;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Interface\ServerInterface;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

final class CoroutineServer implements ServerInterface
{
	private array $servers = [];

	public function __construct(
		private readonly EventDispatcherInterface $eventDispatcher,
		private readonly ConfigInterface          $config,
	)
	{
		foreach ($config->getServerConfigs()->getIterator() as $serverConfig) {
			$this->addServer($serverConfig);
		}
	}

	public function addServer(Config $config): void
	{
		$serverName                   = $config->type->getCoroutineServer();
		$server                       = new $serverName($config->host, $config->port);
		$this->servers[$config->name] = $server;

		$this->eventDispatcher->dispatch(new BeforeServerStart($server, $config, $this->config->getMode()));
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