<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Swoole\Process;
use Swoole\Server;
use Throwable;

use function count;

use const SWOOLE_BASE;

/**
 * Exercises native contracts without opening sockets or starting a server.
 */
final class UserProcessServer extends Server
{
    /** @var list<Process> */
    public array $processes = [];

    public ?int $countAtStart = null;

    /** @var list<bool|null> */
    public array $daemonizeAtAttachment = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(private readonly bool|Throwable $addResult = true)
    {
        $this->mode = SWOOLE_BASE;
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    public function addProcess(Process $process): int|false
    {
        $this->daemonizeAtAttachment[] = $this->setting['daemonize'] ?? null;
        $this->processes[] = $process;
        if ($this->addResult instanceof Throwable) {
            throw $this->addResult;
        }
        return $this->addResult ? count($this->processes) - 1 : false;
    }

    public function start(): bool
    {
        $this->countAtStart = count($this->processes);
        return true;
    }

    /** @param array<string, mixed> $settings */
    public function set(array $settings): bool
    {
        $this->setting = $settings + ($this->setting ?? []);
        return true;
    }
}
