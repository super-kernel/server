<?php

declare(strict_types=1);

namespace SuperKernel\Server\Enum;

/**
 * Selects the native Swoole execution model used by the configured server.
 */
enum Mode: string
{
    case Base = 'base';
    case Process = 'process';
    case Thread = 'thread';
    case Coroutine = 'coroutine';
}
