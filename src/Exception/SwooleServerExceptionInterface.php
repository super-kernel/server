<?php

declare(strict_types=1);

namespace SuperKernel\Server\Exception;

use Throwable;

/**
 * Marks failures owned by the Swoole Server component.
 */
interface SwooleServerExceptionInterface extends Throwable
{
}
