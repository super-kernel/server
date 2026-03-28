<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Event;

use Swoole\Http\Request;
use Swoole\Http\Response;

final class HttpRequestEvent
{
	public function __invoke(Request $request, Response $response): void
	{
		$response->end("<h1>Async server.</h1>");
	}
}