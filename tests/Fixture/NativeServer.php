<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Swoole\Server;
use Swoole\Server\Port;

/**
 * Exercises native contracts without opening sockets or starting a server.
 */
final class NativeServer extends Server
{
    public bool $started = false;

    public bool $setResult = true;

    public ?bool $daemonizeAtStart = null;

    private readonly bool $startResult;

    /**
     * @param list<Port> $ports
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(bool $startResult = true, array $ports = [])
    {
        $this->startResult = $startResult;
        $this->ports = $ports;
    }

    public function start(): bool
    {
        $this->daemonizeAtStart = $this->setting['daemonize'] ?? null;
        $this->started = true;

        return $this->startResult;
    }

    /** @param array<string, mixed> $settings */
    public function set(array $settings): bool
    {
        if (!$this->setResult) {
            return false;
        }

        $this->setting = $settings + ($this->setting ?? []);

        return true;
    }
}
