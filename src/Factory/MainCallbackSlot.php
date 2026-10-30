<?php

declare(strict_types=1);

namespace SuperKernel\Server\Factory;

use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionProperty;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Exception\ServerCreationException;
use Swoole\Server;
use Throwable;

use function get_debug_type;
use function is_callable;
use function sprintf;
use function ucfirst;

/**
 * Resolves one Server-owned main callback handler and binds it to its unoccupied native Server callback slot.
 *
 * @internal
 */
final class MainCallbackSlot
{
    /**
     * A slot counts as occupied when the Server, its main port, or the base Server callback property holds a
     * callback. Every failure is logged once as an error before it is thrown.
     *
     * @param class-string                $callbackHandler
     * @param callable(Server): void|null $observer        runs with the same Server after the handler returns
     *
     * @throws ServerCreationException When the handler cannot be resolved or is not its callable class, the slot is
     *                                 occupied, or native registration fails; a thrown cause is kept as previous
     */
    public static function bind(
        Server                $server,
        ContainerInterface    $container,
        StdoutLoggerInterface $logger,
        string                $eventName,
        string                $callbackHandler,
        ?callable             $observer = null,
    ): void
    {
        try {
            $callback = $container->get($callbackHandler);
        } catch (Throwable $throwable) {
            $message = sprintf(
                'Unable to resolve Swoole Server callback handler %s from the DI container.',
                $callbackHandler,
            );

            $logger->error($message);

            throw new ServerCreationException($message, 0, $throwable);
        }

        if (!$callback instanceof $callbackHandler || !is_callable($callback)) {
            $message = sprintf(
                'DI entry %s must resolve to its callable Swoole Server callback handler; %s provided.',
                $callbackHandler,
                get_debug_type($callback),
            );

            $logger->error($message);

            throw new ServerCreationException($message);
        }

        if (null !== $observer) {
            $callback = static function (Server $server) use ($callback, $observer): void {
                $callback($server);
                $observer($server);
            };
        }

        $message = sprintf(
            'Unable to register Swoole Server event "%s" through callback handler %s.',
            $eventName,
            $callbackHandler,
        );

        try {
            $occupied = null !== $server->getCallback($eventName);

            if (!$occupied && isset($server->ports[0])) {
                $occupied = null !== $server->ports[0]->getCallback($eventName);

                if (!$occupied) {
                    try {
                        $occupied = null !== new ReflectionProperty(
                            Server::class,
                            'on' . ucfirst($eventName),
                        )->getValue($server);
                    } catch (ReflectionException) {
                        $occupied = false;
                    }
                }
            }

            if (!$occupied && $server->on($eventName, $callback)) {
                return;
            }
        } catch (Throwable $throwable) {
            $logger->error($message);

            throw new ServerCreationException($message, 0, $throwable);
        }

        $logger->error($message);

        throw new ServerCreationException($message);
    }
}
