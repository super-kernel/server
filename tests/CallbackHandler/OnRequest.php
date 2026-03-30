<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\CallbackHandler;

use SuperKernel\Attribute\Provider;
use SuperKernel\Server\Contract\Callbacks\OnRequestInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

#[Provider(OnRequestInterface::class)]
final class OnRequest implements OnRequestInterface
{
	private string $serverName;

	public function setServerName(string $serverName): void
	{
		$this->serverName = $serverName;
	}

	public function __invoke(Request $request, Response $response): void
	{
		$response->end('Handle Request Callback Event.');
	}
}