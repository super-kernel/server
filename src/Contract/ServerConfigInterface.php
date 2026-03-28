<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use SuperKernel\Server\Constants\ServerModeConstants;
use SuperKernel\Server\Constants\ServerConstants;

interface ServerConfigInterface
{
	public function getHookFlags(): int;

	public function getType(): ServerConstants;

	public function getMode(): ServerModeConstants;

	public function getServers(): array;

	public function getSettings(): array;
}