<?php
declare(strict_types=1);

namespace SuperKernel\Server;

use Generator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Server\Attribute\CallbackEvent;
use SuperKernel\Server\Constants\TypeConstants;
use SuperKernel\Server\Constants\ModeConstants;
use SuperKernel\Server\Contract\AsynchronousServerInterface;
use SuperKernel\Server\Contract\CallbackEventInterface;
use SuperKernel\Server\Contract\Callbacks\OnAfterReloadInterface;
use SuperKernel\Server\Contract\Callbacks\OnBeforeReloadInterface;
use SuperKernel\Server\Contract\Callbacks\OnBeforeShutdownInterface;
use SuperKernel\Server\Contract\Callbacks\OnCloseInterface;
use SuperKernel\Server\Contract\Callbacks\OnConnectInterface;
use SuperKernel\Server\Contract\Callbacks\OnFinishInterface;
use SuperKernel\Server\Contract\Callbacks\OnManagerStartInterface;
use SuperKernel\Server\Contract\Callbacks\OnManagerStopInterface;
use SuperKernel\Server\Contract\Callbacks\OnPacketInterface;
use SuperKernel\Server\Contract\Callbacks\OnPipeMessageInterface;
use SuperKernel\Server\Contract\Callbacks\OnReceiveInterface;
use SuperKernel\Server\Contract\Callbacks\OnRequestInterface;
use SuperKernel\Server\Contract\Callbacks\OnShutdownInterface;
use SuperKernel\Server\Contract\Callbacks\OnStartInterface;
use SuperKernel\Server\Contract\Callbacks\OnTaskInterface;
use SuperKernel\Server\Contract\Callbacks\OnWorkerErrorInterface;
use SuperKernel\Server\Contract\Callbacks\OnWorkerExitInterface;
use SuperKernel\Server\Contract\Callbacks\OnWorkerStartInterface;
use SuperKernel\Server\Contract\Callbacks\OnWorkerStopInterface;
use SuperKernel\Server\Contract\ServerConfigInterface;
use SuperKernel\Server\Event\BeforeMainServerStart;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;
use Swoole\WebSocket\Server as SwooleWebsocketServer;
use function array_replace;
use function class_implements;
use function is_subclass_of;

#[Provider(AsynchronousServerInterface::class)]
final class AsynchronousServer implements AsynchronousServerInterface
{
	private Server $server;

	/**
	 * @param ContainerInterface       $container
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param ServerConfigInterface    $serverConfig
	 *
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		private readonly ContainerInterface       $container,
		private readonly EventDispatcherInterface $eventDispatcher,
		ServerConfigInterface                     $serverConfig,
	)
	{
		$mode = $serverConfig->getMode();

		foreach ($serverConfig->getConfigs() as $config) {
			$name = $config->getName();
			$type = $config->getType();
			$host = $config->getHost();
			$port = $config->getPort();
			$settings = $config->getSettings();
			$sockType = $config->getSockType();

			if (!isset($this->server)) {
				$this->server = $this->makeServer($type, $mode, $host, $port, $sockType);
				$this->server->set(array_replace($serverConfig->getSettings(), $settings));
				$this->registerEvents($this->server, $name);

				$this->eventDispatcher->dispatch(new BeforeMainServerStart($this->server, $config));
			} else {
				$vassalServer = $this->server->addlistener($host, $port, $sockType);
				if (!$vassalServer) {
					throw new RuntimeException("Failed to listen server port [$host:$port]");
				}
				$vassalServer->set(array_replace($serverConfig->getSettings(), $settings));
				$this->registerEvents($vassalServer, $name);
			}
		}
	}

	private function makeServer(
		TypeConstants $serverType,
		ModeConstants $serverMode, string $host, int|array $port, int $sockType): Server
	{
		$mode = $serverMode->value;

		return match (true) {
			$serverType === TypeConstants::SERVER_BASE      => new Server($host, $port, $mode, $sockType),
			$serverType === TypeConstants::SERVER_HTTP      => new SwooleHttpServer($host, $port, $mode, $sockType),
			$serverType === TypeConstants::SERVER_WEBSOCKET => new SwooleWebsocketServer($host, $port, $mode, $sockType),
		};
	}

	/**
	 * @param Server|ServerPort $server
	 * @param string            $serverName
	 *
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function registerEvents(Server|ServerPort $server, string $serverName): void
	{
		/* @var AnnotationCollectorInterface $annotationCollector */
		$annotationCollector = $this->container->get(AnnotationCollectorInterface::class);

		foreach ($this->getCallbackHandlers() as $callbackHandler) {
			$interfaces = class_implements($callbackHandler);

			if (false === $interfaces) {
				continue;
			}

			foreach ($interfaces as $interface) {
				if (is_subclass_of($interface, CallbackEventInterface::class)) {
					$annotations = $annotationCollector->getClassAttributes($interface);

					foreach ($annotations as $annotation) {
						if (is_subclass_of($annotation->getClass(), CallbackEventInterface::class)) {
							/* @var CallbackEvent $callbackEvent */
							$callbackEvent = $annotation->getInstance();

							$callbackHandler->setServerName($serverName);
							$server->on($callbackEvent->getEventName(), [$callbackHandler, '__invoke']);
						}
					}
				}
			}
		}
	}

	/**
	 * @return Generator
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function getCallbackHandlers(): Generator
	{
		$this->container->has(OnAfterReloadInterface::class) && yield $this->container->get(OnAfterReloadInterface::class);
		$this->container->has(OnBeforeReloadInterface::class) && yield $this->container->get(OnBeforeReloadInterface::class);
		$this->container->has(OnBeforeShutdownInterface::class) && yield $this->container->get(OnBeforeShutdownInterface::class);
		$this->container->has(OnCloseInterface::class) && yield $this->container->get(OnCloseInterface::class);
		$this->container->has(OnConnectInterface::class) && yield $this->container->get(OnConnectInterface::class);
		$this->container->has(OnFinishInterface::class) && yield $this->container->get(OnFinishInterface::class);
		$this->container->has(OnManagerStartInterface::class) && yield $this->container->get(OnManagerStartInterface::class);
		$this->container->has(OnManagerStopInterface::class) && yield $this->container->get(OnManagerStopInterface::class);
		$this->container->has(OnPacketInterface::class) && yield $this->container->get(OnPacketInterface::class);
		$this->container->has(OnPipeMessageInterface::class) && yield $this->container->get(OnPipeMessageInterface::class);
		$this->container->has(OnReceiveInterface::class) && yield $this->container->get(OnReceiveInterface::class);
		$this->container->has(OnRequestInterface::class) && yield $this->container->get(OnRequestInterface::class);
		$this->container->has(OnShutdownInterface::class) && yield $this->container->get(OnShutdownInterface::class);
		$this->container->has(OnStartInterface::class) && yield $this->container->get(OnStartInterface::class);
		$this->container->has(OnTaskInterface::class) && yield $this->container->get(OnTaskInterface::class);
		$this->container->has(OnWorkerErrorInterface::class) && yield $this->container->get(OnWorkerErrorInterface::class);
		$this->container->has(OnWorkerExitInterface::class) && yield $this->container->get(OnWorkerExitInterface::class);
		$this->container->has(OnWorkerStartInterface::class) && yield $this->container->get(OnWorkerStartInterface::class);
		$this->container->has(OnWorkerStopInterface::class) && yield $this->container->get(OnWorkerStopInterface::class);
	}

	public function start(): void
	{
		$this->server->start();
	}
}