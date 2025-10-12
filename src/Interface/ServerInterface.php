<?php
declare(strict_types=1);

namespace SuperKernel\Server\Interface;

use SuperKernel\Server\ServerConfig;
use SuperKernel\Server\Mode;

interface ServerInterface
{
	public function setMode(Mode $mode): ServerInterface;

	public function addServer(ServerConfig $config): void;

    public function start(): void;
}