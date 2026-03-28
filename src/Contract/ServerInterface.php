<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use Swoole\Server;

interface ServerInterface
{
	public function configuration(): void;

	public function getServer(): Server;

	public function start(): void;
}