<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

interface ServerInterface
{
	public function start(): void;
}