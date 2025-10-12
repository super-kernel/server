<?php
declare(strict_types=1);

namespace SuperKernel\Server\Factory\ServerHandler;

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\Mode;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Interface\ServerInterface;
use Swoole\Coroutine;
use function Swoole\Coroutine\run;

final class CoroutineServer implements ServerInterface
{
	private array $servers = [];

	private Mode $mode;

	public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
	{
	}

	public function setMode(Mode $mode): ServerInterface
	{
		$this->mode = $mode;

		return $this;
	}

	public function addServer(ServerConfig $config): void
	{
		$serverName                   = $config->type->getCoroutineServer();
		$server                       = new $serverName($config->host, $config->port);
		$this->servers[$config->name] = $server;

		$this->eventDispatcher->dispatch(new BeforeServerStart($server, $config, $this->mode));
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