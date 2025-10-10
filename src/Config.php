<?php
declare(strict_types=1);

namespace SuperKernel\Server;

final readonly class Config
{
	/**
	 * @param string     $name
	 * @param ServerType $type
	 * @param string     $host
	 * @param int        $port
	 * @param int        $sock_type
	 */
	public function __construct(
		public string     $name,
		public ServerType $type,
		public string     $host,
		public int        $port,
		public int        $sock_type,
	)
	{
	}
}