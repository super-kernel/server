<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Swoole\Coroutine;
use Swoole\Coroutine\Server;

/**
 * Exercises native contracts without opening sockets or starting a server.
 */
final class NativeCoroutineServer extends Server
{
    public mixed $handler = null;

    public bool $started = false;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(
        private readonly bool $startResult = true,
        private readonly bool $yieldOnStart = false,
    )
    {
        $this->host = '127.0.0.1';
        $this->port = 9501;
    }

    public function handle(callable $fn): void
    {
        $this->handler = $fn;
    }

    public function start(): bool
    {
        $this->started = true;

        if ($this->yieldOnStart) {
            Coroutine::sleep(0.001);
        }

        return $this->startResult;
    }
}
