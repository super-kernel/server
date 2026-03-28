<?php
declare(strict_types=1);

namespace SuperKernelTest\Server\CallbackHandler;

use SuperKernel\Attribute\Provider;
use SuperKernel\Server\Contract\Callbacks\onRequestInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

#[Provider(onRequestInterface::class)]
final class onRequest implements onRequestInterface
{
	private string $serverName;

	public function setServerName(string $serverName): void
	{
		$this->serverName = $serverName;
	}

	public function __invoke(Request $request, Response $response): void
	{
		var_dump('Handle Request Callback Event.');
	}
}