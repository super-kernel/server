<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Callbacks\AfterReloadCallback;
use SuperKernel\Server\Callbacks\BeforeReloadCallback;
use SuperKernel\Server\Callbacks\BeforeShutdownCallback;
use SuperKernel\Server\Callbacks\FinishCallback;
use SuperKernel\Server\Callbacks\ManagerStartCallback;
use SuperKernel\Server\Callbacks\ManagerStopCallback;
use SuperKernel\Server\Callbacks\PipeMessageCallback;
use SuperKernel\Server\Callbacks\ShutdownCallback;
use SuperKernel\Server\Callbacks\StartCallback;
use SuperKernel\Server\Callbacks\TaskCallback;
use SuperKernel\Server\Callbacks\WorkerErrorCallback;
use SuperKernel\Server\Callbacks\WorkerExitCallback;
use SuperKernel\Server\Callbacks\WorkerStartCallback;
use SuperKernel\Server\Callbacks\WorkerStopCallback;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Constants\Protocol;
use SuperKernel\Server\Exception\ServerCreationException;
use SuperKernel\Server\Exception\UnsupportedServerModeException;
use SuperKernel\Server\Factory\MainCallbackSlot;
use SuperKernel\Server\Factory\SwooleServerBuilder;
use Swoole\Constant;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;
use Swoole\Thread;

use function class_exists;
use function constant;
use function count;
use function defined;
use function getmypid;
use function is_int;
use function sprintf;

use const PHP_ZTS;
use const SWOOLE_BASE;
use const SWOOLE_PROCESS;

#[FactoryProvider(Server::class)]
final readonly class SwooleServerProvider
{
    /** @var array<string, class-string> */
    private const array CALLBACK_HANDLERS = [
        Constant::EVENT_START           => StartCallback::class,
        Constant::EVENT_BEFORE_SHUTDOWN => BeforeShutdownCallback::class,
        Constant::EVENT_SHUTDOWN        => ShutdownCallback::class,
        Constant::EVENT_WORKER_START    => WorkerStartCallback::class,
        Constant::EVENT_WORKER_STOP     => WorkerStopCallback::class,
        Constant::EVENT_BEFORE_RELOAD   => BeforeReloadCallback::class,
        Constant::EVENT_AFTER_RELOAD    => AfterReloadCallback::class,
        Constant::EVENT_TASK            => TaskCallback::class,
        Constant::EVENT_FINISH          => FinishCallback::class,
        Constant::EVENT_WORKER_EXIT     => WorkerExitCallback::class,
        Constant::EVENT_WORKER_ERROR    => WorkerErrorCallback::class,
        Constant::EVENT_MANAGER_START   => ManagerStartCallback::class,
        Constant::EVENT_MANAGER_STOP    => ManagerStopCallback::class,
        Constant::EVENT_PIPE_MESSAGE    => PipeMessageCallback::class,
    ];

    public function __invoke(
        SwooleServerConfigInterface $configuration,
        ContainerInterface          $container,
        StdoutLoggerInterface       $logger,
    ): Server
    {
        $serverMode = $configuration->mode();

        if (Mode::Thread === $serverMode && (!PHP_ZTS || !defined('SWOOLE_THREAD') || !class_exists(Thread::class))) {
            throw new UnsupportedServerModeException(
                'Swoole thread mode requires a ZTS PHP runtime and an ext-swoole build with thread support.',
            );
        }

        $nativeMode = match ($serverMode) {
            Mode::Base      => SWOOLE_BASE,
            Mode::Process   => SWOOLE_PROCESS,
            Mode::Thread    => constant('SWOOLE_THREAD'),
            Mode::Coroutine => throw new UnsupportedServerModeException(
                'Swoole coroutine mode must be created through CoroutineServerProvider.',
            ),
        };

        if (!is_int($nativeMode)) {
            throw new UnsupportedServerModeException('The SWOOLE_THREAD mode constant must be an integer.');
        }

        $listeners = $configuration->listeners();
        $server = SwooleServerBuilder::build($configuration, $listeners, $serverMode, $nativeMode);
        $nativeServers = [
            $server,
        ];

        for ($index = 1, $count = count($listeners); $index < $count; ++$index) {
            $port = $server->ports[$index] ?? null;

            if (!$port instanceof ServerPort) {
                throw new ServerCreationException(sprintf(
                    'The native Swoole Server did not retain listener "%s" at position %d.',
                    $listeners[$index]['name'],
                    $index + 1,
                ));
            }

            $nativeServers[] = $port;
        }

        $startupLogger = static function (Server $server) use ($logger, $serverMode, $listeners, $nativeServers): void {
            $logger->notice(sprintf('Swoole main server started in %s mode.', $serverMode->value));

            $processId = getmypid();

            $logger->info(sprintf(
                'Swoole server process started with PID %s.',
                false === $processId ? '-' : $processId,
            ));

            foreach ($listeners as $index => $listener) {
                $nativeServer = 0 === $index ? $server : $nativeServers[$index];

                $logger->info(sprintf(
                    'Swoole listener "%s" started on %s:%d (%s).',
                    $listener['name'],
                    $nativeServer->host,
                    $nativeServer->port,
                    ($listener['protocol'] ?? Protocol::Raw)->value,
                ));
            }
        };

        foreach (self::CALLBACK_HANDLERS as $eventName => $callbackHandler) {
            MainCallbackSlot::bind(
                $server,
                $container,
                $logger,
                $eventName,
                $callbackHandler,
                Constant::EVENT_START === $eventName ? $startupLogger : null,
            );
        }

        return $server;
    }
}
