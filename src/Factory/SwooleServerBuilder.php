<?php

declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use SuperKernel\Server\Config\ProtocolCapability;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Constants\Protocol;
use SuperKernel\Server\Exception\ServerCreationException;
use Swoole\Server;
use Throwable;

use function count;
use function sprintf;

use const SWOOLE_SOCK_TCP;

/**
 * Builds the configured native main server, applies its settings, and adds later listener ports in order.
 *
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 *
 * @internal
 */
final class SwooleServerBuilder
{
    /**
     * Reads settings once from the configuration after constructing the primary listener.
     *
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @throws ServerCreationException When the main server, its settings, a later port, or a port protocol is
     *                                 rejected; a thrown native cause is preserved as the previous exception
     */
    public static function build(
        SwooleServerConfigInterface $configuration,
        array                       $listeners,
        Mode                        $serverMode,
        int                         $nativeMode,
    ): Server
    {
        $primary = $listeners[0];

        try {
            $server = MainServerCreator::create($primary, $nativeMode);
        } catch (Throwable $throwable) {
            throw new ServerCreationException(sprintf(
                'Unable to create the Swoole %s-mode server for listener "%s".',
                $serverMode->value,
                $primary['name'],
            ), 0, $throwable);
        }

        $settings = $configuration->settings();

        if ([] !== $settings) {
            try {
                $configured = $server->set($settings);
            } catch (Throwable $throwable) {
                throw new ServerCreationException(sprintf(
                    'Unable to apply settings to the Swoole %s-mode server.',
                    $serverMode->value,
                ), 0, $throwable);
            }

            if (!$configured) {
                throw new ServerCreationException(sprintf(
                    'Unable to apply settings to the Swoole %s-mode server.',
                    $serverMode->value,
                ));
            }
        }

        for ($index = 1, $count = count($listeners); $index < $count; ++$index) {
            $listener = $listeners[$index];

            try {
                $port = $server->listen(
                    $listener['host'],
                    $listener['port'],
                    $listener['socketType'] ?? SWOOLE_SOCK_TCP,
                );
            } catch (Throwable $throwable) {
                throw new ServerCreationException(sprintf(
                    'Unable to create Swoole listener "%s" at position %d.',
                    $listener['name'],
                    $index + 1,
                ), 0, $throwable);
            }

            if (false === $port) {
                throw new ServerCreationException(sprintf(
                    'Unable to create Swoole listener "%s" at position %d.',
                    $listener['name'],
                    $index + 1,
                ));
            }

            try {
                $port->set(ProtocolCapability::portSettings($listener['protocol'] ?? Protocol::Raw));
            } catch (Throwable $throwable) {
                throw new ServerCreationException(sprintf(
                    'Unable to configure the "%s" protocol for Swoole listener "%s" at position %d.',
                    ($listener['protocol'] ?? Protocol::Raw)->value,
                    $listener['name'],
                    $index + 1,
                ), 0, $throwable);
            }
        }

        return $server;
    }
}
