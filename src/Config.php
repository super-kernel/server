<?php
declare(strict_types=1);

namespace SuperKernel\Server;

use SuperKernel\Server\Constants\TypeConstants;

final readonly class Config
{
	public function __construct(
		private string        $name,
		private TypeConstants $type,
		private string        $host,
		private int           $port,
		private int           $sockType,
		private array         $settings = [],
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getType(): TypeConstants
	{
		return $this->type;
	}

	public function getHost(): string
	{
		return $this->host;
	}

	public function getPort(): int
	{
		return $this->port;
	}

	public function getSockType(): int
	{
		return $this->sockType;
	}

	public function getSettings(): array
	{
		return $this->settings;
	}
}