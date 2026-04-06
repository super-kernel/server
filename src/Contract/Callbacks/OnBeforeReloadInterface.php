<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnBeforeReloadInterface
{
	public function __invoke(Server $server): void;
}