<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use SuperKernel\Swoole\Config\SwooleConfigInterface;

final readonly class SwooleConfig implements SwooleConfigInterface
{
    public function daemon(): bool
    {
        return false;
    }

    public function pidFile(): ?string
    {
        return null;
    }

    public function displayErrors(): bool
    {
        return true;
    }

    public function socketBufferSize(): int
    {
        return 8388608;
    }

    public function enablePreemptiveScheduler(): bool
    {
        return false;
    }
}
