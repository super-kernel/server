<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Swoole\Server\Port;

/**
 * Exercises native contracts without opening sockets or starting a server.
 */
final class NativeServerPort extends Port
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
    }
}
