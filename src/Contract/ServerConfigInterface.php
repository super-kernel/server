<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use SuperKernel\Server\Config;
use SuperKernel\Server\Constants\ModeConstants;

interface ServerConfigInterface
{
	public function getMode(): ModeConstants;

	public function getHookFlags(): int;

	/**
	 * @return iterable<Config>
	 */
	public function getConfigs(): iterable;

	public function getSettings(): array;
}