<?php

declare(strict_types=1);

namespace SuperKernel\Server\Startup;

use Psr\Container\ContainerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Exception\ServerStartException;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;
use Throwable;

use function get_debug_type;
use function sprintf;

/**
 * Resolves the exact container entry selected by the configured mode and validates its native service type.
 *
 * @internal
 */
final class ServerEntryResolver
{
    /**
     * Each call performs a new container lookup; callers retain the result.
     *
     * @throws ServerStartException When the configured container entry cannot resolve the expected native service
     */
    public static function resolve(
        SwooleServerConfigInterface $configuration,
        ContainerInterface          $container,
    ): Server|CoroutineServer
    {
        $serverClass = Mode::Coroutine === $configuration->mode()
            ? CoroutineServer::class
            : Server::class;

        try {
            $server = $container->get($serverClass);
        } catch (Throwable $throwable) {
            throw new ServerStartException(sprintf(
                'Unable to resolve the configured Swoole service from container entry %s.',
                $serverClass,
            ), 0, $throwable);
        }

        if (!$server instanceof $serverClass) {
            throw new ServerStartException(sprintf(
                'Container entry %s must resolve to that native Swoole service; %s provided.',
                $serverClass,
                get_debug_type($server),
            ));
        }

        return $server;
    }
}
