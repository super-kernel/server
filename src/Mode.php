<?php
declare(strict_types=1);

namespace SuperKernel\Server;

enum Mode: int
{
	case SWOOLE_DISABLE = 0;
	case SWOOLE_BASE    = 1;
	case SWOOLE_PROCESS = 2;
	case SWOOLE_THREAD  = 3;
}