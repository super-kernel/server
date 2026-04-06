<?php
declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SuperKernel\Attribute\Autowired;
use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Provider;
use SuperKernel\Server\Constants\TypeConstants;
use SuperKernel\Server\Contract\ServerConfigInterface;
use SuperKernel\Server\Event\BeforeMainServerStart;
use SuperKernel\Server\Event\BeforeServerStart;
use Swoole\Coroutine;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server;
use Swoole\Server\Port;
use Swoole\WebSocket\Server as SwooleWebsocketServer;
use function array_replace;

#[
	Provider(Server::class),
	Factory,
]
final class SwooleServerProvider
{
	#[Autowired]
	protected readonly EventDispatcherInterface $eventDispatcher;

	private Server $server;

	public function __invoke(EventDispatcherInterface $eventDispatcher, ServerConfigInterface $serverConfig): Server
	{
		if ($serverConfig->getSettings()['event_object'] ?? false) {
			throw new RuntimeException('Object-style event callbacks are not currently supported.');
		}

		Coroutine::set(['hook_flags' => $serverConfig->getHookFlags()]);

		$mode = $serverConfig->getMode()->value;

		foreach ($serverConfig->getConfigs() as $config) {
			$name = $config->getName();
			$type = $config->getType();
			$host = $config->getHost();
			$port = $config->getPort();
			$settings = array_replace($serverConfig->getSettings(), $config->getSettings());
			$sockType = $config->getSockType();

			if (!isset($this->server)) {
				$this->server = $this->makeMainServer($type, $mode, $host, $port, $sockType, $settings);

				$this->eventDispatcher->dispatch(new BeforeMainServerStart($this->server));
			} else {
				$vassalServer = $this->makeVassalServer($host, $port, $sockType, $settings);

				$this->eventDispatcher->dispatch(new BeforeServerStart($name, $vassalServer));
			}
		}

		return $this->server;
	}

	private function makeMainServer(TypeConstants $type, int $mode, string $host, int $port, int $sockType, array $settings): Server
	{
		$server = match (true) {
			$type === TypeConstants::SERVER_BASE      => new Server($host, $port, $mode, $sockType),
			$type === TypeConstants::SERVER_HTTP      => new SwooleHttpServer($host, $port, $mode, $sockType),
			$type === TypeConstants::SERVER_WEBSOCKET => new SwooleWebsocketServer($host, $port, $mode, $sockType),
		};

		$server->set($settings);

		return $server;
	}

	private function makeVassalServer(string $host, int $port, int $sockType, array $settings): Port
	{
		$vassalServer = $this->server->addlistener($host, $port, $sockType);

		if (!$vassalServer) {
			throw new RuntimeException("Failed to listen server port [$host:$port]");
		}

		$vassalServer->set($settings);

		return $vassalServer;
	}
}