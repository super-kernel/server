<?php
declare(strict_types=1);

namespace Event;

use SuperKernel\Server\Attribute\Event;
use SuperKernel\Server\Enumeration\CoroutineServerEvent;
use SuperKernel\Server\Interface\EventInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

#[Event(CoroutineServerEvent::SERVER_HTTP_HANDLE)]
final class CoroutineHttpRequestEvent implements EventInterface
{
	public function __invoke(Request $request, Response $response): void
	{
		$response->end("<h1>Coroutine server.</h1>");
	}
}