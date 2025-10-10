<?php
declare(strict_types=1);

namespace Event;

use SuperKernel\Server\Attribute\Event;
use SuperKernel\Server\Enumeration\HttpServerEvent;
use SuperKernel\Server\Interface\EventInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

#[Event(HttpServerEvent::ON_REQUEST)]
final class HttpRequestEvent implements EventInterface
{
	public function __invoke(Request $request, Response $response): void
	{
		$response->end("<h1>Async server.</h1>");
	}
}