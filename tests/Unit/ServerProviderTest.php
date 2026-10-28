<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Event\AfterMainServerCreated;
use SuperKernel\Server\Event\AfterServerCreated;
use SuperKernel\Server\Exception\ServerStartException;
use SuperKernel\Server\Listener\MainProcessCreatedListener;
use SuperKernel\Server\Manager\UserProcessManager;
use SuperKernel\Server\Provider\ServerProvider;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Server\Tests\Fixture\ConfigurationRegistry;
use SuperKernel\Server\Tests\Fixture\NativeCoroutineServer;
use SuperKernel\Server\Tests\Fixture\NativeEventServer;
use SuperKernel\Server\Tests\Fixture\NativeServer;
use SuperKernel\Server\Tests\Fixture\NativeServerPort;
use SuperKernel\Server\Tests\Fixture\RecordingEventDispatcher;
use SuperKernel\Server\Tests\Fixture\RecordingStdoutLogger;
use SuperKernel\Server\Tests\Fixture\ServerContainer;
use SuperKernel\Server\Tests\Fixture\SwooleConfig;
use SuperKernel\Server\Tests\Fixture\SwooleServerConfig;
use SuperKernel\Swoole\Event\MainProcessCreated;
use SuperKernel\Swoole\MainProcess;
use Swoole\Coroutine;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Server;
use Swoole\Timer;
use Throwable;

use function file_get_contents;
use function file_put_contents;
use function getmypid;
use function json_decode;
use function json_encode;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class ServerProviderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDispatchesMainAndEveryNativeListenerBeforeStarting(): void
    {
        $primaryPort = new NativeServerPort();
        $additionalPort = new NativeServerPort();
        $server = new NativeServer(ports: [
            $primaryPort,
            $additionalPort,
        ]);
        $mainListener = [
            'name' => 'main',
            'host' => '127.0.0.1',
            'port' => 9501,
        ];
        $extraListener = [
            'name' => 'extra',
            'host' => '127.0.0.1',
            'port' => 9502,
        ];
        $configuration = new SwooleServerConfig(Mode::Process, [
            $mainListener,
            $extraListener,
        ]);
        $dispatcher = new RecordingEventDispatcher(static function () use ($server): void {
            self::assertFalse($server->started);
            $server->set([
                'daemonize'  => true,
                'worker_num' => 2,
            ]);
        });
        $provider = $this->provider($configuration, $server, $dispatcher);

        $provider->start();

        self::assertTrue($server->started);
        self::assertFalse($server->daemonizeAtStart);
        self::assertSame(2, $server->setting['worker_num']);
        self::assertCount(3, $dispatcher->events);

        $mainEvent = $dispatcher->events[0];
        self::assertInstanceOf(AfterMainServerCreated::class, $mainEvent);
        self::assertSame($server, $mainEvent->server());

        $firstListenerEvent = $dispatcher->events[1];
        self::assertInstanceOf(AfterServerCreated::class, $firstListenerEvent);
        self::assertSame($server, $firstListenerEvent->server());
        self::assertSame($mainListener, $firstListenerEvent->configuration());

        $secondListenerEvent = $dispatcher->events[2];
        self::assertInstanceOf(AfterServerCreated::class, $secondListenerEvent);
        self::assertSame($additionalPort, $secondListenerEvent->server());
        self::assertSame($extraListener, $secondListenerEvent->configuration());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRetainsTheResolvedNativeIdentityAndAllowsOnlyOneStartAttempt(): void
    {
        $server = new NativeServer();
        $dispatcher = new RecordingEventDispatcher();
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Base),
            $server,
            $dispatcher,
        );

        self::assertSame($server, $provider->getServer());
        self::assertSame($server, $provider->getServer());

        $provider->start();

        try {
            $provider->start();
            self::fail('A second startup attempt must fail.');
        } catch (ServerStartException $exception) {
            self::assertStringContainsString('only be started once', $exception->getMessage());
        }

        self::assertCount(2, $dispatcher->events);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDoesNotRetrofitFactoryOwnedCallbacksOntoACustomNativeProvider(): void
    {
        $server = new NativeEventServer();
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Process),
            $server,
        );

        self::assertSame($server, $provider->getServer());
        self::assertSame([], $server->eventNames());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDispatchFailurePreventsNativeStartupAndConsumesTheSingleAttempt(): void
    {
        $failure = new RuntimeException('listener failed');
        $server = new NativeServer();
        $dispatcher = new RecordingEventDispatcher(static function () use ($failure): never {
            throw $failure;
        });
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Process),
            $server,
            $dispatcher,
        );

        try {
            $provider->start();
            self::fail('A bootstrap listener failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertFalse($server->started);
        self::assertCount(1, $dispatcher->events);

        $this->expectException(ServerStartException::class);
        $this->expectExceptionMessageIsOrContains('only be started once');

        $provider->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectedDaemonSettingPreventsStartupAndConsumesTheAttempt(): void
    {
        $server = new NativeServer();
        $server->setResult = false;
        $dispatcher = new RecordingEventDispatcher();
        $manager = $this->createMock(UserProcessManagerInterface::class);
        $manager->expects(self::never())->method('all');
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Base),
            $server,
            $dispatcher,
            userProcesses: $manager,
        );

        try {
            $provider->start();
            self::fail('Rejected settings must prevent startup.');
        } catch (ServerStartException $exception) {
            self::assertStringContainsString('disable Swoole server daemonization', $exception->getMessage());
        }

        self::assertFalse($server->started);
        self::assertCount(2, $dispatcher->events);

        $this->expectException(ServerStartException::class);
        $this->expectExceptionMessageIsOrContains('only be started once');
        $provider->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReportsANativeStartupFailure(): void
    {
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Process),
            new NativeServer(false),
        );

        $this->expectException(ServerStartException::class);
        $this->expectExceptionMessageIsOrContains('failed to start');

        $provider->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testTreatsTheSoleCoroutineServiceAsTheLogicalMainAndLogsAfterYield(): void
    {
        $listener = [
            'name' => 'coroutine',
            'host' => '127.0.0.1',
            'port' => 9501,
        ];
        $configuration = new SwooleServerConfig(Mode::Coroutine, [
            $listener,
        ]);
        $server = new NativeCoroutineServer(yieldOnStart: true);
        $dispatcher = new RecordingEventDispatcher();
        $logger = new RecordingStdoutLogger();
        $manager = $this->createMock(UserProcessManagerInterface::class);
        $manager->expects(self::never())->method('all');
        $provider = $this->provider($configuration, $server, $dispatcher, $logger, $manager);

        $provider->start();

        self::assertTrue($server->started);
        self::assertNull($server->handler);
        self::assertSame($server, $provider->getServer());
        self::assertCount(2, $dispatcher->events);
        self::assertInstanceOf(AfterMainServerCreated::class, $dispatcher->events[0]);
        self::assertSame($server, $dispatcher->events[0]->server());
        self::assertInstanceOf(AfterServerCreated::class, $dispatcher->events[1]);
        self::assertSame($server, $dispatcher->events[1]->server());
        self::assertSame($listener, $dispatcher->events[1]->configuration());

        $processId = getmypid();
        self::assertNotFalse($processId);
        self::assertSame([
            [
                'level'   => LogLevel::NOTICE,
                'message' => 'Swoole main server started in coroutine mode.',
                'context' => [],
            ],
            [
                'level'   => LogLevel::INFO,
                'message' => sprintf('Swoole server process started with PID %d.', $processId),
                'context' => [],
            ],
            [
                'level'   => LogLevel::INFO,
                'message' => 'Swoole listener "coroutine" started on 127.0.0.1:9501 (raw).',
                'context' => [],
            ],
        ], $logger->records);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReportsCoroutineFailureWithoutLoggingAFalseStart(): void
    {
        $logger = new RecordingStdoutLogger();
        $provider = $this->provider(
            new SwooleServerConfig(Mode::Coroutine),
            new NativeCoroutineServer(false),
            logger: $logger,
        );

        try {
            $provider->start();
            self::fail('A rejected coroutine startup must fail.');
        } catch (ServerStartException $exception) {
            self::assertStringContainsString('coroutine server failed to start', $exception->getMessage());
        }

        self::assertSame([], $logger->records);
    }

    /** @throws Throwable if native setup or Server startup fails */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMainProcessRunsARealCoroutineServerThroughTheServerOwnedRuntime(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'sk-coroutine-server-');
        $container = $this->createStub(ContainerInterface::class);
        $container->method(
            'get',
        )->willReturnCallback(function (string $id) use ($log): ServerInterface|SwooleServerConfigInterface {
            if (SwooleServerConfigInterface::class === $id) {
                return new SwooleServerConfig(Mode::Coroutine);
            }
            $server = new CoroutineServer('127.0.0.1', 0);
            $server->handle(static function (): void {
            });
            $logger = $this->createStub(StdoutLoggerInterface::class);
            $logger->method('notice')->willReturnCallback(static function () use ($server, $log): void {
                file_put_contents($log, json_encode([
                    'pid'       => getmypid(),
                    'coroutine' => Coroutine::getCid(),
                ], JSON_THROW_ON_ERROR));
                Timer::after(1, static function () use ($server): void {
                    $server->shutdown();
                });
            });
            return $this->provider(
                new SwooleServerConfig(Mode::Coroutine),
                $server,
                logger: $logger,
            );
        });
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new SwooleServerConfig(Mode::Coroutine),
        ]);
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
        self::assertSame('', file_get_contents($log));

        try {
            $main->start();
            $result = json_decode(file_get_contents($log), true, flags: JSON_THROW_ON_ERROR);
            self::assertNotSame(getmypid(), $result['pid']);
            self::assertGreaterThanOrEqual(0, $result['coroutine']);
            self::assertLessThan(0, Coroutine::getCid());
        } finally {
            unlink($log);
        }
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function provider(
        SwooleServerConfig           $configuration,
        Server|CoroutineServer       $server,
        ?RecordingEventDispatcher    $dispatcher = null,
        ?StdoutLoggerInterface       $logger = null,
        ?UserProcessManagerInterface $userProcesses = null,
    ): ServerProvider
    {
        return new ServerProvider(
            new SwooleServerConfigProvider()(new ConfigurationRegistry([
                SwooleServerConfigInterface::class => $configuration,
            ])),
            new ServerContainer($server),
            $dispatcher ?? new RecordingEventDispatcher(),
            $logger ?? new RecordingStdoutLogger(),
            $userProcesses ?? new UserProcessManager(),
        );
    }
}
