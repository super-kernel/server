<?php
declare(strict_types=1);

namespace SuperKernel\Server\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SplPriorityQueue;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Contract\ReflectionManagerInterface;
use SuperKernel\Server\Attribute\Event;
use SuperKernel\Server\Event\BeforeServerStart;
use SuperKernel\Server\Mode;
use SuperKernel\Server\ServerType;

#[
	Listener([
		BeforeServerStart::class,
	]),
]
final class BeforeServerStartListener implements ListenerInterface
{
	/**
	 * @var array<string,array<string,SplPriorityQueue>> $events
	 */
	private array $events;

	/**
	 * @param ContainerInterface         $container
	 * @param ReflectionManagerInterface $reflectionManager
	 *
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(ContainerInterface $container, ReflectionManagerInterface $reflectionManager)
	{
		$classes = $reflectionManager->getAttributes(Event::class);

		foreach ($classes as $class) {
			$attributes = $reflectionManager->getClassAnnotations($class, Event::class);

			foreach ($attributes as $attribute) {
				/* @var Event $attributeInstance */
				$attributeInstance = $attribute->newInstance();

				$queue = $this->events[$attributeInstance->server][$attributeInstance->event] ?? new SplPriorityQueue();

				$queue->insert($container->get($class), $attributeInstance->priority);

				$this->events[$attributeInstance->server][$attributeInstance->event] = $queue;
			}
		}
	}

	/**
	 * @param object                    $event
	 *
	 * @phpstan-param BeforeServerStart $event
	 *
	 * @return void
	 */
	public function process(object $event): void
	{
		$serverName = $this->getServer($event->mode, $event->config->type);

		foreach ($this->events[$serverName] ?? [] as $eventName => $serverEvent) {
			$eventQueue   = clone $serverEvent;
			$eventHandler = [$eventQueue->top(), '__invoke'];

			if ($event->mode === Mode::SWOOLE_DISABLE) {
				match ($event->config->type) {
					ServerType::SERVER_TCP,
					ServerType::SERVER_UDP       => $event->server->handle($eventHandler),
					ServerType::SERVER_HTTP,
					ServerType::SERVER_WEBSOCKET => $event->server->handle('/', $eventHandler),
				};
			} else {
				$event->server->on($eventName, $eventHandler);
			}
		}
	}

	private function getServer(Mode $mode, ServerType $serverType): string
	{
		return $mode === Mode::SWOOLE_DISABLE
			? $serverType->getCoroutineServer()
			: $serverType->getServer();
	}
}