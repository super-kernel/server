<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

interface AsynchronousServerInterface
{
	public function start(): void;
}