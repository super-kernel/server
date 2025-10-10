<?php
declare(strict_types=1);

namespace SuperKernel\Server\Attribute;

use Attribute;
use RuntimeException;
use SuperKernel\Server\Enumeration\CoroutineServerEvent;
use SuperKernel\Server\Enumeration\HttpServerEvent;
use SuperKernel\Server\Enumeration\ServerEvent;
use SuperKernel\Server\Enumeration\WebsocketServerEvent;
use SuperKernel\Server\ServerType;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Event
{
	public string $server;

	public string $event;

	public function __construct(
		private ServerEvent|HttpServerEvent|WebsocketServerEvent|CoroutineServerEvent $swooleEvent,
		public int                                                                    $priority = 0,
	)
	{
		$this->server = match (true) {
			$this->swooleEvent instanceof ServerEvent                            => ServerType::SERVER_TCP->getServer(),
			$this->swooleEvent instanceof HttpServerEvent                        => ServerType::SERVER_HTTP->getServer(),
			$this->swooleEvent instanceof WebsocketServerEvent                   => ServerType::SERVER_WEBSOCKET->getServer(),
			$this->swooleEvent === CoroutineServerEvent::SERVER_HANDLE           => ServerType::SERVER_TCP->getCoroutineServer(),
			$this->swooleEvent === CoroutineServerEvent::SERVER_HTTP_HANDLE      => ServerType::SERVER_HTTP->getCoroutineServer(),
			$this->swooleEvent === CoroutineServerEvent::SERVER_WEBSOCKET_HANDLE => ServerType::SERVER_WEBSOCKET->getCoroutineServer(),
			default                                                              => throw new RuntimeException('No event handler matched.'),
		};

		$this->event = $this->swooleEvent->value;
	}
}