<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract\Callbacks;

use Swoole\Server;

interface OnPacketInterface
{
	public function __invoke(Server $server, string $data, array $clientInfo): void;
}