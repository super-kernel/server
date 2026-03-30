<?php
declare(strict_types=1);

namespace SuperKernel\Server\Contract;

use SuperKernel\Server\Config;
use SuperKernel\Server\Constants\ModeConstants;
use SuperKernel\Server\Constants\ServerConstants;

interface ServerInterface
{
	public function getType(): ServerConstants;

	public function getMode(): ModeConstants;

	public function getHookFlags(): int;

	/**
	 * @return iterable<Config>
	 */
	public function getServers(): iterable;

	public function getSettings(): array;
}