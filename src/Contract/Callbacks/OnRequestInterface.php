<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use SuperKernel\Server\Attribute\CallbackEvent;
use SuperKernel\Server\Contract\CallbackEventInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

#[CallbackEvent(event: 'request')]
interface OnRequestInterface extends CallbackEventInterface
{
	public function __invoke(Request $request, Response $response): void;
}