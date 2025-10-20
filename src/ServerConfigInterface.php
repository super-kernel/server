<?php
declare (strict_types=1);

namespace SuperKernel\Server;

interface ServerConfigInterface
{
	public function getMode(): Mode;

	/**
	 * @return array<ServerConfig>
	 */
	public function getServers(): array;

	public function getSettings(): array;
}