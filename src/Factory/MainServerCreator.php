<?php

declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Protocol;
use Swoole\Exception;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\WebSocket\Server as WebSocketServer;

use const SWOOLE_SOCK_TCP;

/**
 * Creates the native main server class selected by the primary listener's protocol.
 *
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 *
 * @internal
 */
final class MainServerCreator
{
    /**
     * @psalm-param ListenerConfiguration $listener
     *
     * @throws Exception When the native server cannot be constructed
     */
    public static function create(
        array $listener,
        int   $mode,
    ): Server
    {
        return match ($listener['protocol'] ?? Protocol::Raw) {
            Protocol::Raw => new Server(
                $listener['host'],
                $listener['port'],
                $mode,
                $listener['socketType'] ?? SWOOLE_SOCK_TCP,
            ),
            Protocol::Http => new HttpServer(
                $listener['host'],
                $listener['port'],
                $mode,
                $listener['socketType'] ?? SWOOLE_SOCK_TCP,
            ),
            Protocol::WebSocket => new WebSocketServer(
                $listener['host'],
                $listener['port'],
                $mode,
                $listener['socketType'] ?? SWOOLE_SOCK_TCP,
            ),
        };
    }
}
