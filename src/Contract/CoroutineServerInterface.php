<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

interface CoroutineServerInterface
{
	public function start(): void;
}