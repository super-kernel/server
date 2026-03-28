<?php
declare(strict_types=1);

namespace SuperKernel\Server;

use Exception;
use SuperKernel\Server\Contract\CoroutineServerInterface;

final class CoroutineServer implements CoroutineServerInterface
{
	/**
	 * @return void
	 * @throws Exception
	 */
	public function start(): void
	{
		throw new Exception('Coroutine server is not currently supported.');
	}
}