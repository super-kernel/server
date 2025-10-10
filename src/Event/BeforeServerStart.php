<?php
declare(strict_types=1);

namespace SuperKernel\Server\Event;

use SuperKernel\Server\Config;
use SuperKernel\Server\Mode;
use Swoole\Coroutine\Http\Server as HttpServer;
use Swoole\Coroutine\Server;
use Swoole\Server as SwooleServer;

final class BeforeServerStart
{
	public function __construct(
		public SwooleServer|HttpServer|Server $server,
		public Config                         $config,
		public Mode                           $mode,
	)
	{
	}
}