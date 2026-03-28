<?php
declare(strict_types=1);

namespace SuperKernel\Server\Constants;

use SuperKernel\Server\Contract\AsynchronousServerInterface;
use SuperKernel\Server\Contract\CoroutineServerInterface;

enum ServerConstants: string
{
	case Asynchronous = AsynchronousServerInterface::class;

	case Coroutine = CoroutineServerInterface::class;
}