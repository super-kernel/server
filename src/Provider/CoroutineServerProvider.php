<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Exception\ServerCreationException;
use SuperKernel\Server\Exception\UnsupportedServerModeException;
use Swoole\Coroutine\Server as CoroutineServer;
use Throwable;

use function class_exists;
use function sprintf;

#[FactoryProvider(CoroutineServer::class)]
final readonly class CoroutineServerProvider
{
    public function __invoke(SwooleServerConfigInterface $configuration): CoroutineServer
    {
        if (Mode::Coroutine !== $configuration->mode()) {
            throw new UnsupportedServerModeException(
                'Swoole native modes must be created through SwooleServerProvider.',
            );
        }

        $listener = $configuration->listeners()[0];

        if (!class_exists(CoroutineServer::class)) {
            throw new UnsupportedServerModeException(
                'Swoole coroutine mode requires the ext-swoole userland coroutine server library.',
            );
        }

        try {
            $server = new CoroutineServer(
                $listener['host'],
                $listener['port'],
                $listener['ssl'] ?? false,
                $listener['reusePort'] ?? false,
            );
            $server->set($configuration->settings());
        } catch (Throwable $throwable) {
            throw new ServerCreationException(
                sprintf(
                    'Unable to create the Swoole coroutine server for listener "%s".',
                    $listener['name'],
                ),
                0,
                $throwable,
            );
        }

        return $server;
    }
}
