<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

interface CallbackEventInterface
{
	public function setServerName(string $serverName): void;
}