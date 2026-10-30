<?php

declare(strict_types=1);

namespace SuperKernel\Server\Startup;

use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Constants\Protocol;
use SuperKernel\Server\Exception\ServerStartException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Server as CoroutineServer;
use Throwable;

use function getmypid;
use function sprintf;

/**
 * Starts a coroutine server inside the current coroutine runtime and logs startup after native start yields.
 *
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 *
 * @internal
 */
final class CoroutineServerStartup
{
    /**
     * Must run inside a coroutine. Startup is logged only while native start has not yet reported a result; a
     * logging failure shuts the server down before it propagates.
     *
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @throws ServerStartException When coroutine creation, result delivery, or startup is rejected
     * @throws Throwable            When native coroutine startup or startup logging throws
     */
    public static function start(CoroutineServer $server, array $listeners, StdoutLoggerInterface $logger): void
    {
        /** @var Channel<array{started: bool}|array{failure: Throwable}> $result */
        $result = new Channel();

        $coroutineId = Coroutine::create(static function () use ($server, $result): void {
            try {
                $started = $server->start();
            } catch (Throwable $throwable) {
                $result->push([
                    'failure' => $throwable,
                ]);

                return;
            }

            $result->push([
                'started' => $started,
            ]);
        });

        if (false === $coroutineId) {
            throw new ServerStartException('Unable to create the Swoole server coroutine.');
        }

        if ($result->isEmpty()) {
            try {
                $nativeServers = [
                    $server,
                ];

                $logger->notice(sprintf('Swoole main server started in %s mode.', Mode::Coroutine->value));

                $processId = getmypid();

                $logger->info(sprintf(
                    'Swoole server process started with PID %s.',
                    false === $processId ? '-' : $processId,
                ));

                foreach ($listeners as $index => $listener) {
                    $nativeServer = $nativeServers[$index];

                    $logger->info(sprintf(
                        'Swoole listener "%s" started on %s:%d (%s).',
                        $listener['name'],
                        $nativeServer->host,
                        $nativeServer->port,
                        ($listener['protocol'] ?? Protocol::Raw)->value,
                    ));
                }
            } catch (Throwable $throwable) {
                try {
                    $server->shutdown();
                } catch (Throwable) {
                }

                throw $throwable;
            }
        }

        /** @var array{started: bool}|array{failure: Throwable}|false $outcome */
        $outcome = $result->pop();

        if (false === $outcome) {
            throw new ServerStartException('Unable to read the Swoole server coroutine result.');
        }

        if (isset($outcome['failure'])) {
            throw $outcome['failure'];
        }

        if (!$outcome['started']) {
            throw new ServerStartException('The Swoole coroutine server failed to start.');
        }
    }
}
