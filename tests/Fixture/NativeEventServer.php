<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Closure;
use Swoole\Server;

use function array_keys;

/**
 * Exercises native contracts without opening sockets or starting a server.
 */
final class NativeEventServer extends Server
{
    /**
     * @var array<string, Closure>
     */
    private array $callbacks = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(private readonly ?string $failedEvent = null)
    {
    }

    public function on(string $event_name, callable $callback): bool
    {
        if ($event_name === $this->failedEvent) {
            return false;
        }

        $this->callbacks[$event_name] = $callback(...);

        return true;
    }

    /**
     * Matches the Swoole 6.2 runtime; ide-helper declares a broader callable return type.
     *
     * @noinspection PhpHierarchyChecksInspection
     */
    public function getCallback(string $event_name): Closure|array|string|null
    {
        return $this->callbacks[$event_name] ?? null;
    }

    public function callback(string $eventName): Closure
    {
        return $this->callbacks[$eventName];
    }

    /**
     * @return list<string>
     */
    public function eventNames(): array
    {
        return array_keys($this->callbacks);
    }
}
