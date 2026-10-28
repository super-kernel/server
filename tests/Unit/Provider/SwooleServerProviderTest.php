<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use ReflectionProperty;
use RuntimeException;
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
use SuperKernel\Server\Event\Callbacks\OnStart;
use SuperKernel\Server\Event\Callbacks\OnTask;
use SuperKernel\Server\Event\Callbacks\OnWorkerError;
use SuperKernel\Server\Exception\ServerCreationException;
use SuperKernel\Server\Exception\UnsupportedServerModeException;
use SuperKernel\Server\Provider\CoroutineServerProvider;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Server\Provider\SwooleServerProvider;
use SuperKernel\Server\Tests\Fixture\ConfigurationRegistry;
use SuperKernel\Server\Tests\Fixture\RecordingEventDispatcher;
use SuperKernel\Server\Tests\Fixture\RecordingStdoutLogger;
use SuperKernel\Server\Tests\Fixture\SwooleServerConfig;
use Swoole\Constant;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Event;
use Swoole\Http\Server as HttpServer;
use Swoole\Server;
use Swoole\Thread;
use Swoole\WebSocket\Server as WebSocketServer;
use Throwable;

use function array_keys;
use function array_values;
use function class_exists;
use function defined;
use function getmypid;
use function sprintf;
use function ucfirst;

use const PHP_ZTS;
use const SWOOLE_BASE;
use const SWOOLE_PROCESS;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SwooleServerProviderTest extends TestCase
{
    /** @return iterable<string, array{Mode, int}> */
    public static function nativeModes(): iterable
    {
        yield 'base' => [
            Mode::Base,
            SWOOLE_BASE,
        ];
        yield 'process' => [
            Mode::Process,
            SWOOLE_PROCESS,
        ];
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    #[DataProvider('nativeModes')]
    public function testCreatesAndConfiguresNativeModes(Mode $mode, int $nativeMode): void
    {
        $server = $this->createNative(new SwooleServerConfig($mode, settings: [
            'worker_num' => 1,
        ]));

        self::assertSame($nativeMode, $server->mode);
        self::assertSame('127.0.0.1', $server->host);
        self::assertSame(1, $server->setting['worker_num']);
    }

    /** @return iterable<string, array{Protocol, class-string<Server>}> */
    public static function primaryProtocols(): iterable
    {
        yield 'http' => [
            Protocol::Http,
            HttpServer::class,
        ];
        yield 'websocket' => [
            Protocol::WebSocket,
            WebSocketServer::class,
        ];
    }

    /**
     * @param class-string<Server> $serverClass
     *
     * @throws Throwable When fixture setup or the operation under test throws
     */
    #[DataProvider('primaryProtocols')]
    public function testSelectsThePrimaryNativeClassFromItsProtocol(
        Protocol $protocol,
        string   $serverClass,
    ): void
    {
        $server = $this->createNative(new SwooleServerConfig(Mode::Base, [
            [
                'name'     => $protocol->value,
                'host'     => '127.0.0.1',
                'port'     => 0,
                'protocol' => $protocol,
            ],
        ]));
        self::assertInstanceOf($serverClass, $server);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testConfiguresEveryAdditionalPortWithItsOwnProtocol(): void
    {
        $server = $this->createNative(new SwooleServerConfig(Mode::Process, [
            [
                'name'     => 'websocket',
                'host'     => '127.0.0.1',
                'port'     => 0,
                'protocol' => Protocol::WebSocket,
            ],
            [
                'name'     => 'http',
                'host'     => '127.0.0.1',
                'port'     => 0,
                'protocol' => Protocol::Http,
            ],
            [
                'name' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 0,
            ],
        ]));

        self::assertSame([
            'open_http_protocol'      => true,
            'open_websocket_protocol' => false,
        ], $server->ports[1]->setting);
        self::assertSame([
            'open_http_protocol'      => false,
            'open_http2_protocol'     => false,
            'open_websocket_protocol' => false,
        ], $server->ports[2]->setting);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRegistersMainCallbacksAndStartupLoggingOnlyDuringNativeFactoryCreation(): void
    {
        $processId = getmypid();
        self::assertNotFalse($processId);

        $logger = new RecordingStdoutLogger();
        $server = $this->createNative(
            new SwooleServerConfig(Mode::Process, [
                [
                    'name' => 'main',
                    'host' => '127.0.0.1',
                    'port' => 0,
                ],
            ]),
            $logger,
        );
        $callback = self::registeredCallback($server, Constant::EVENT_START);
        $callback($server);

        self::assertSame([
            [
                'level'   => LogLevel::NOTICE,
                'message' => 'Swoole main server started in process mode.',
                'context' => [],
            ],
            [
                'level'   => LogLevel::INFO,
                'message' => sprintf('Swoole server process started with PID %d.', $processId),
                'context' => [],
            ],
            [
                'level'   => LogLevel::INFO,
                'message' => sprintf(
                    'Swoole listener "main" started on %s:%d (raw).',
                    $server->host,
                    $server->port,
                ),
                'context' => [],
            ],
        ], $logger->records);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testResolvesAndRegistersEveryCallbackHandlerThroughTheNativeFactory(): void
    {
        $dispatcher = new RecordingEventDispatcher(static function (object $event): void {
            if ($event instanceof OnTask) {
                $event->result = 'task result';
            }
        });

        /** @var list<class-string> $resolved */
        $resolved = [];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($dispatcher, &$resolved): object {
                $resolved[] = $id;

                return new $id($dispatcher);
            },
        );
        $server = $this->createNative(
            new SwooleServerConfig(Mode::Process),
            dispatcher: $dispatcher,
            container: $container,
        );

        self::assertSame(array_values(self::callbackHandlers()), $resolved);

        foreach (array_keys(self::callbackHandlers()) as $eventName) {
            self::assertIsCallable(self::registeredCallback($server, $eventName));
        }

        self::registeredCallback($server, Constant::EVENT_START)($server);
        self::assertSame(
            'task result',
            self::registeredCallback($server, Constant::EVENT_TASK)($server, 7, 3, 'payload'),
        );
        self::registeredCallback($server, Constant::EVENT_WORKER_ERROR)($server, 6, 1234, 255, 15);

        self::assertInstanceOf(OnStart::class, $dispatcher->events[0]);
        self::assertSame($server, $dispatcher->events[0]->server);
        self::assertInstanceOf(OnTask::class, $dispatcher->events[1]);
        self::assertSame('payload', $dispatcher->events[1]->data);
        self::assertInstanceOf(OnWorkerError::class, $dispatcher->events[2]);
        self::assertSame(15, $dispatcher->events[2]->signal);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testLogsAndStopsWhenACallbackHandlerCannotBeResolved(): void
    {
        $failure = new RuntimeException('resolution failed');
        $dispatcher = new RecordingEventDispatcher();
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($dispatcher, $failure): object {
                if (WorkerErrorCallback::class === $id) {
                    throw $failure;
                }

                return new $id($dispatcher);
            },
        );
        $logger = new RecordingStdoutLogger();

        try {
            $this->createNative(
                new SwooleServerConfig(Mode::Process),
                $logger,
                $dispatcher,
                $container,
            );
            self::fail('Expected callback resolution to stop server creation.');
        } catch (ServerCreationException $exception) {
            self::assertStringContainsString(
                'Unable to resolve Swoole Server callback handler',
                $exception->getMessage(),
            );
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertSame(LogLevel::ERROR, $logger->records[0]['level']);
        self::assertStringContainsString(
            WorkerErrorCallback::class,
            (string) $logger->records[0]['message'],
        );
        self::assertSame([], $logger->records[0]['context']);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testObservesNativeStartupAfterOnStartEventDispatch(): void
    {
        $logger = new RecordingStdoutLogger();
        $dispatcher = new RecordingEventDispatcher(
            static function (object $event) use ($logger): void {
                if ($event instanceof OnStart) {
                    $logger->debug(sprintf(
                        'Application started %s:%d.',
                        $event->server->host,
                        $event->server->port,
                    ));
                }
            },
        );

        $server = $this->createNative(
            new SwooleServerConfig(Mode::Process),
            $logger,
            $dispatcher,
        );
        $callback = self::registeredCallback($server, Constant::EVENT_START);
        $callback($server);

        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level']);
        self::assertSame(
            sprintf('Application started %s:%d.', $server->host, $server->port),
            $logger->records[0]['message'],
        );
        self::assertSame(LogLevel::NOTICE, $logger->records[1]['level']);
        self::assertSame('Swoole main server started in process mode.', $logger->records[1]['message']);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testThreadModeIsCapabilityGated(): void
    {
        $configuration = new SwooleServerConfig(Mode::Thread);

        if (PHP_ZTS && defined('SWOOLE_THREAD') && class_exists(Thread::class)) {
            self::assertInstanceOf(Server::class, $this->createNative($configuration));

            return;
        }

        $this->expectException(UnsupportedServerModeException::class);
        $this->expectExceptionMessageIsOrContains('requires a ZTS PHP runtime');

        $this->createNative($configuration);
    }

    /** @return iterable<string, array{array{ssl?: bool, reusePort?: bool}, bool}> */
    public static function coroutineOptions(): iterable
    {
        yield 'defaults' => [
            [],
            false,
        ];
        yield 'ssl' => [
            [
                'ssl' => true,
            ],
            true,
        ];
        yield 'explicit flags' => [
            [
                'ssl'       => false,
                'reusePort' => true,
            ],
            false,
        ];
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @param array{ssl?: bool, reusePort?: bool} $options
     *
     * @throws ContainerExceptionInterface When the fixture configuration cannot be loaded
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    #[DataProvider('coroutineOptions')]
    public function testCoroutineFactoryOwnsTheSoleRawCoroutineService(array $options, bool $ssl): void
    {
        $configuration = new SwooleServerConfig(Mode::Coroutine, [
            [
                'name' => 'coroutine',
                'host' => '127.0.0.1',
                'port' => 0,
                ...$options,
            ],
        ]);
        $server = new CoroutineServerProvider()(
            new SwooleServerConfigProvider()(new ConfigurationRegistry([
                SwooleServerConfigInterface::class => $configuration,
            ])),
        );

        try {
            self::assertInstanceOf(CoroutineServer::class, $server);
            self::assertSame('127.0.0.1', $server->host);
            self::assertSame($ssl, $server->setting['open_ssl']);
        } finally {
            unset($server);
            Event::wait();
        }
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function createNative(
        SwooleServerConfig        $configuration,
        ?RecordingStdoutLogger    $logger = null,
        ?RecordingEventDispatcher $dispatcher = null,
        ?ContainerInterface       $container = null,
    ): Server
    {
        $dispatcher ??= new RecordingEventDispatcher();
        if (null === $container) {
            $container = $this->createStub(ContainerInterface::class);
            $container->method('get')->willReturnCallback(
                static fn(string $id): object => new $id($dispatcher),
            );
        }
        $logger ??= new RecordingStdoutLogger();

        return new SwooleServerProvider()(
            new SwooleServerConfigProvider()(new ConfigurationRegistry([
                SwooleServerConfigInterface::class => $configuration,
            ])),
            $container,
            $logger,
        );
    }

    /**
     * @return array<string, class-string>
     */
    private static function callbackHandlers(): array
    {
        return [
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
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private static function registeredCallback(Server $server, string $eventName): callable
    {
        $callback = $server->getCallback($eventName)
            ?? new ReflectionProperty(Server::class, 'on' . ucfirst($eventName))->getValue($server);

        self::assertIsCallable($callback);

        return $callback;
    }
}
