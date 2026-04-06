<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface OnRequestInterface
{
	public function __invoke(Request $request, Response $response): void;
}