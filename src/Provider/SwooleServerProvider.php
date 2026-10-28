<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionProperty;
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
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Enum\Protocol;
use SuperKernel\Server\Exception\ServerCreationException;
use SuperKernel\Server\Exception\UnsupportedServerModeException;
use Swoole\Constant;
use Swoole\Exception;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;
use Swoole\Thread;
use Swoole\WebSocket\Server as WebSocketServer;
use Throwable;

use function class_exists;
use function constant;
use function count;
use function defined;
use function get_debug_type;
use function getmypid;
use function is_callable;
use function is_int;
use function sprintf;
use function ucfirst;

use const PHP_ZTS;
use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const SWOOLE_SOCK_TCP;

/**
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 */
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
        $nativeMode = match ($serverMode) {
            Mode::Base      => SWOOLE_BASE,
            Mode::Process   => SWOOLE_PROCESS,
            Mode::Thread    => $this->threadMode(),
            Mode::Coroutine => throw new UnsupportedServerModeException(
                'Swoole coroutine mode must be created through CoroutineServerProvider.',
            ),
        };
        $listeners = $configuration->listeners();

        $server = $this->createServer($configuration, $listeners, $serverMode, $nativeMode);
        $nativeServers = $this->nativeServers($server, $listeners);

        $this->registerCallbackHandlers(
            $server,
            $container,
            $logger,
            $this->startupLogger($logger, $serverMode, $listeners, $nativeServers),
        );

        return $server;
    }

    /**
     * @param non-empty-list<ListenerConfiguration> $listeners
     */
    private function createServer(
        SwooleServerConfigInterface $configuration,
        array                       $listeners,
        Mode                        $serverMode,
        int                         $nativeMode,
    ): Server
    {
        $primary = $listeners[0];

        try {
            $server = $this->createMainServer($primary, $nativeMode);
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
                $port->set($this->portProtocolSettings($listener['protocol'] ?? Protocol::Raw));
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

    /**
     * @psalm-param ListenerConfiguration $listener
     *
     * @throws Exception When the native server cannot be constructed
     */
    private function createMainServer(
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

    /**
     * @return array<string, bool>
     */
    private function portProtocolSettings(Protocol $protocol): array
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

    /**
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @return non-empty-list<Server|ServerPort>
     */
    private function nativeServers(Server $server, array $listeners): array
    {
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

        return $nativeServers;
    }

    /**
     * @param Closure(Server): void $onStartObserver
     */
    private function registerCallbackHandlers(
        Server                $server,
        ContainerInterface    $container,
        StdoutLoggerInterface $logger,
        Closure               $onStartObserver,
    ): void
    {
        foreach (self::CALLBACK_HANDLERS as $eventName => $callbackHandler) {
            $this->registerCallbackHandler(
                $server,
                $container,
                $logger,
                $eventName,
                $callbackHandler,
                Constant::EVENT_START === $eventName ? $onStartObserver : null,
            );
        }
    }

    /**
     * @param class-string                $callbackHandler
     * @param callable(Server): void|null $observer
     */
    private function registerCallbackHandler(
        Server                $server,
        ContainerInterface    $container,
        StdoutLoggerInterface $logger,
        string                $eventName,
        string                $callbackHandler,
        ?callable             $observer = null,
    ): void
    {
        $callback = $this->resolveCallbackHandler($container, $logger, $callbackHandler);

        if (null !== $observer) {
            $callback = $this->withObserver($callback, $observer);
        }

        $this->registerCallback($server, $logger, $eventName, $callbackHandler, $callback);
    }

    /**
     * @param class-string $callbackHandler
     */
    private function resolveCallbackHandler(
        ContainerInterface    $container,
        StdoutLoggerInterface $logger,
        string                $callbackHandler,
    ): callable
    {
        try {
            $callback = $container->get($callbackHandler);
        } catch (Throwable $throwable) {
            $this->failCallbackRegistration(
                $logger,
                sprintf(
                    'Unable to resolve Swoole Server callback handler %s from the DI container.',
                    $callbackHandler,
                ),
                $throwable,
            );
        }

        if (!$callback instanceof $callbackHandler || !is_callable($callback)) {
            $this->failCallbackRegistration($logger, sprintf(
                'DI entry %s must resolve to its callable Swoole Server callback handler; %s provided.',
                $callbackHandler,
                get_debug_type($callback),
            ));
        }

        return $callback;
    }

    /**
     * @param class-string $callbackHandler
     */
    private function registerCallback(
        Server                $server,
        StdoutLoggerInterface $logger,
        string                $eventName,
        string                $callbackHandler,
        callable              $callback,
    ): void
    {
        $message = sprintf(
            'Unable to register Swoole Server event "%s" through callback handler %s.',
            $eventName,
            $callbackHandler,
        );

        try {
            if (!$this->hasCallback($server, $eventName) && $server->on($eventName, $callback)) {
                return;
            }
        } catch (Throwable $throwable) {
            $this->failCallbackRegistration($logger, $message, $throwable);
        }

        $this->failCallbackRegistration($logger, $message);
    }

    private function hasCallback(Server $server, string $eventName): bool
    {
        if (null !== $server->getCallback($eventName)) {
            return true;
        }

        if (!isset($server->ports[0])) {
            return false;
        }

        if (null !== $server->ports[0]->getCallback($eventName)) {
            return true;
        }

        try {
            return null !== new ReflectionProperty(
                Server::class,
                'on' . ucfirst($eventName),
            )->getValue($server);
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @param callable(Server): void $callback
     * @param callable(Server): void $observer
     *
     * @return Closure(Server): void
     */
    private function withObserver(callable $callback, callable $observer): Closure
    {
        return static function (Server $server) use ($callback, $observer): void {
            $callback($server);
            $observer($server);
        };
    }

    private function failCallbackRegistration(
        StdoutLoggerInterface $logger,
        string                $message,
        ?Throwable            $previous = null,
    ): never
    {
        $logger->error($message);

        throw new ServerCreationException($message, 0, $previous);
    }

    /**
     * @param non-empty-list<ListenerConfiguration> $listeners
     * @param non-empty-list<Server|ServerPort>     $nativeServers
     *
     * @return Closure(Server): void
     */
    private function startupLogger(
        StdoutLoggerInterface $logger,
        Mode                  $mode,
        array                 $listeners,
        array                 $nativeServers,
    ): Closure
    {
        return static function (Server $server) use ($logger, $mode, $listeners, $nativeServers): void {
            $logger->notice(sprintf('Swoole main server started in %s mode.', $mode->value));

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
    }

    private function threadMode(): int
    {
        if (!PHP_ZTS || !defined('SWOOLE_THREAD') || !class_exists(Thread::class)) {
            throw new UnsupportedServerModeException(
                'Swoole thread mode requires a ZTS PHP runtime and an ext-swoole build with thread support.',
            );
        }

        $mode = constant('SWOOLE_THREAD');

        if (!is_int($mode)) {
            throw new UnsupportedServerModeException('The SWOOLE_THREAD mode constant must be an integer.');
        }

        return $mode;
    }
}
