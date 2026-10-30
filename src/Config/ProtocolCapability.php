<?php

declare(strict_types=1);

namespace SuperKernel\Server\Config;

use SuperKernel\Server\Constants\Protocol;

/**
 * Describes each listener protocol's native capability: its rank and the settings that enable it on a port.
 *
 * @internal
 */
final class ProtocolCapability
{
    /**
     * @return int<0, 2> the capability order WebSocket > Http > Raw
     */
    public static function rank(Protocol $protocol): int
    {
        return match ($protocol) {
            Protocol::Raw       => 0,
            Protocol::Http      => 1,
            Protocol::WebSocket => 2,
        };
    }

    /**
     * @return array<string, bool> native port settings that enable exactly this protocol's capability
     */
    public static function portSettings(Protocol $protocol): array
    {
        return match ($protocol) {
            Protocol::Raw => [
                'open_http_protocol'      => false,
                'open_http2_protocol'     => false,
                'open_websocket_protocol' => false,
            ],
            Protocol::Http => [
                'open_http_protocol'      => true,
                'open_websocket_protocol' => false,
            ],
            Protocol::WebSocket => [
                'open_http_protocol'      => true,
                'open_websocket_protocol' => true,
            ],
        };
    }
}
