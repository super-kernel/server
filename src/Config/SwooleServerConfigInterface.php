<?php

declare(strict_types=1);

namespace SuperKernel\Server\Config;

use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Constants\Protocol;

/**
 * Supplies the application-owned input used to configure one Swoole service.
 *
 * Register an implementation with Configuration. Server's factory validates this source and returns the same
 * object for interface injection. Getters must remain stable while the configured service is in use.
 *
 * @psalm-type ListenerConfiguration = array{
 *     name: non-empty-string,
 *     host: non-empty-string,
 *     port: int,
 *     socketType?: int,
 *     ssl?: bool,
 *     reusePort?: bool,
 *     protocol?: Protocol
 * }
 */
interface SwooleServerConfigInterface
{
    /** Returns whether the main-process listener mounts this Server service. */
    public function enable(): bool;

    /**
     * Returns the native execution model selected for the server.
     */
    public function mode(): Mode;

    /**
     * Returns the listeners configured for the service, with the first entry acting as the primary listener.
     *
     * Every listener must have a non-empty, case-sensitive name that is unique within this list.
     * In native modes, the primary listener must declare a protocol capability at least as high as every
     * additional listener.
     * Base, process, and thread modes support additional listeners. Coroutine mode requires exactly one.
     *
     * Missing optional fields use TCP, disabled SSL and port reuse, and the raw protocol.
     *
     * @return non-empty-list<ListenerConfiguration>
     */
    public function listeners(): array;

    /**
     * Returns native Swoole settings applied before the assembled service is exposed.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;
}
