<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\Callbacks;

use SuperKernel\Server\Contract\Callbacks\OnRequestInterface;
use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class OnRequest implements OnRequestInterface
{
	public function __invoke(Request $request, Response $response): void
	{
		var_dump(
			Coroutine::getCid(),
		);

		Coroutine::create(function () {
			var_dump(445);
		});

		$response->end('Handle Request Callbacks Event.');
	}
}