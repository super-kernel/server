<?php
declare(strict_types=1);

namespace SuperKernel\Server\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SplPriorityQueue;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\AttributeCollectorInterface;
use SuperKernel\Contract\ListenerInterface;
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
	 * @param ContainerInterface          $container
	 * @param AttributeCollectorInterface $attributeCollector
	 *
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(ContainerInterface $container, AttributeCollectorInterface $attributeCollector)
	{
		foreach ($attributeCollector->getAttributes(Event::class) as $class => $attributes) {

			/* @var Event $attribute */
			foreach ($attributes as $attribute) {
				/* @var Event $attributeInstance */
				$queue = $this->events[$attribute->server][$attribute->event] ?? new SplPriorityQueue();

				$queue->insert($container->get($class), $attribute->priority);

				$this->events[$attribute->server][$attribute->event] = $queue;
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
			$eventHandler = [
				$eventQueue->top(),
				'__invoke',
			];

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