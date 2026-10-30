<?php

declare(strict_types=1);

namespace SuperKernel\Server\Constants;

/**
 * Selects the native protocol capability required by one Swoole listener.
 */
enum Protocol: string
{
    case Raw = 'raw';
    case Http = 'http';
    case WebSocket = 'websocket';
}
