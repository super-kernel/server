<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Attribute\Listener;
use SuperKernel\Config\Exception\ConfigurationLoadingException;
use SuperKernel\Config\Exception\ConfigurationNotFoundException;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\ConfigInterface;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Attribute\UserProcess;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Exception\InvalidServerConfigurationException;
use SuperKernel\Server\Exception\ServerStartException;
use SuperKernel\Server\Listener\MainProcessCreatedListener;
use SuperKernel\Server\Manager\UserProcessManager;
use SuperKernel\Server\Provider\ServerProvider;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Server\Provider\UserProcessManagerProvider;
use SuperKernel\Server\Tests\Fixture\ConfigurationRegistry;
use SuperKernel\Server\Tests\Fixture\ConfiguredUserProcess;
use SuperKernel\Server\Tests\Fixture\RecordingEventDispatcher;
use SuperKernel\Server\Tests\Fixture\RecordingStdoutLogger;
use SuperKernel\Server\Tests\Fixture\ServerContainer;
use SuperKernel\Server\Tests\Fixture\SwooleConfig;
use SuperKernel\Server\Tests\Fixture\SwooleServerConfig;
use SuperKernel\Server\Tests\Fixture\UserProcessServer;
use SuperKernel\Swoole\Event\MainProcessCreated;
use SuperKernel\Swoole\Exception\SupervisorException;
use SuperKernel\Swoole\MainProcess;
use Swoole\Process;
use Swoole\Server;
use Throwable;

use function file_get_contents;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class UserProcessAttachmentTest extends TestCase
{
    /**
     * The callback retains the signature required by its registration or reflection contract.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testWorkersRegisteredDuringCreationEventsAreAttachedBeforeNativeStart(): void
    {
        $manager = new UserProcessManager();
        $server = new UserProcessServer();
        $dispatcher = new RecordingEventDispatcher(static function () use ($manager, $server): void {
            self::assertSame([], $server->processes);
            $manager->add(
                static function (Process $process, Server $server): void {
                    self::fail('Attachment must not execute a user task.');
                },
            );
        });
        $provider = new ServerProvider(
            new SwooleServerConfig(Mode::Base),
            new ServerContainer($server),
            $dispatcher,
            new RecordingStdoutLogger(),
            $manager,
        );
        self::assertSame([], $manager->all());
        $provider->start();

        self::assertCount(2, $manager->all());
        self::assertSame(2, $server->countAtStart);
        self::assertCount(2, $server->processes);
        self::assertSame([
            false,
            false,
        ], $server->daemonizeAtAttachment);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testProviderAttachesWorkersBeforeNativeStart(): void
    {
        $resolutions = 0;
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ConfiguredUserProcess::class)
            ->willReturnCallback(static function () use (&$resolutions): ConfiguredUserProcess {
                ++$resolutions;
                return new ConfiguredUserProcess('/unused');
            });
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('workers', workNums: 2),
            ],
        ], $container);
        $server = new UserProcessServer();
        $dispatcher = new RecordingEventDispatcher(static function () use ($server): void {
            self::assertSame([], $server->processes);
            $server->set([
                'daemonize' => true,
            ]);
        });
        $provider = new ServerProvider(
            new SwooleServerConfig(Mode::Base),
            new ServerContainer($server, $container),
            $dispatcher,
            new RecordingStdoutLogger(),
            $manager,
        );
        self::assertSame(1, $resolutions);
        self::assertNull($server->countAtStart);
        self::assertSame([], $server->processes);
        $provider->start();

        self::assertSame(1, $resolutions);
        self::assertSame(2, $server->countAtStart);
        self::assertSame([
            false,
            false,
        ], $server->daemonizeAtAttachment);
        self::assertCount(2, $server->processes);
        self::assertNotSame($server->processes[0], $server->processes[1]);
        foreach ($server->processes as $process) {
            self::assertNull($process->pid);
            self::assertGreaterThan(0, $process->pipe);
        }
        $this->expectException(ServerStartException::class);
        $this->expectExceptionMessageIsOrContains('only be started once');
        $provider->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerDefersServerResolutionDuringRegistration(): void
    {
        $source = $this->createMock(SwooleServerConfigInterface::class);
        $source->expects(self::once())->method('enable')->willReturn(true);
        $source->expects(self::never())->method('mode');
        $source->expects(self::never())->method('listeners');
        $source->expects(self::never())->method('settings');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willReturn($source);
        $config = $this->createMock(ConfigInterface::class);
        $config->expects(self::once())->method('has')->with(SwooleServerConfigInterface::class)->willReturn(true);
        $config->expects(self::never())->method('get');
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        $listener = new MainProcessCreatedListener($container, $config);
        self::assertCount(1, new ReflectionClass($listener)->getAttributes(Listener::class));
        self::assertSame([
            MainProcessCreated::class,
        ], $listener->listen());
        $listener->process(new MainProcessCreated($main));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerSkipsAnUnregisteredApplicationConfigurationSource(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::never())->method('get');
        $config = $this->createMock(ConfigInterface::class);
        $config->expects(self::once())->method('has')->with(SwooleServerConfigInterface::class)->willReturn(false);
        $config->expects(self::never())->method('get');
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
        $main->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerRejectsAnIncompatibleDeclaredConfiguration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willReturn(new stdClass());
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new stdClass(),
        ]);
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        $this->expectException(InvalidServerConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(SwooleServerConfigInterface::class);

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
        $main->start();
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerSkipsADisabledServer(): void
    {
        $configuration = $this->createMock(SwooleServerConfigInterface::class);
        $configuration->expects(self::once())->method('enable')->willReturn(false);
        $configuration->expects(self::never())->method('mode');
        $configuration->expects(self::never())->method('listeners');
        $configuration->expects(self::never())->method('settings');
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => $configuration,
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willReturn($configuration);
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
        $main->start();
    }

    /** @throws Throwable when resolving the declared invalid configuration */
    public function testMainListenerValidatesDeclaredConfigurationEvenWhenDisabled(): void
    {
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new SwooleServerConfig(Mode::Base, [], enable: false),
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willReturnCallback(static fn(): SwooleServerConfigInterface => new SwooleServerConfigProvider()($config));
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        $this->expectException(InvalidServerConfigurationException::class);

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerPropagatesConfigurationLoadingFailures(): void
    {
        $failure = new ConfigurationLoadingException('Unable to load Server configuration.');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willThrowException($failure);
        $config = $this->createMock(ConfigInterface::class);
        $config->expects(self::once())->method('has')->with(SwooleServerConfigInterface::class)->willReturn(true);
        $config->expects(self::never())->method('get');
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        $this->expectExceptionObject($failure);

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerPreservesMissingDependenciesOfADeclaredSource(): void
    {
        $failure = new ConfigurationNotFoundException('A nested configuration source is missing.');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');
        $container->expects(self::once())->method('get')->with(SwooleServerConfigInterface::class)
            ->willThrowException($failure);
        $config = $this->createMock(ConfigInterface::class);
        $config->expects(self::once())->method('has')->with(SwooleServerConfigInterface::class)->willReturn(true);
        $config->expects(self::never())->method('get');
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        $this->expectExceptionObject($failure);

        new MainProcessCreatedListener($container, $config)->process(new MainProcessCreated($main));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMainListenerRejectsInvalidDiServerOnlyWhenItsCallbackRuns(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'sk-invalid-di-server-');
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(static function (string $id) use ($log): stdClass|SwooleServerConfigInterface {
                if (SwooleServerConfigInterface::class === $id) {
                    return new SwooleServerConfig(Mode::Base);
                }
                file_put_contents($log, $id);
                return new stdClass();
            });
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new SwooleServerConfig(Mode::Base),
        ]);
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        new MainProcessCreatedListener($container, $config)->process(
            new MainProcessCreated($main),
        );
        self::assertSame('', file_get_contents($log));
        $this->expectException(SupervisorException::class);
        try {
            $main->start();
        } finally {
            self::assertSame(ServerInterface::class, file_get_contents($log));
            unlink($log);
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testServerStartFailureIsReportedByTheSupervisor(): void
    {
        $failure = new RuntimeException('Server startup failed.');
        $server = $this->createStub(ServerInterface::class);
        $server->method('start')->willThrowException($failure);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [
                SwooleServerConfigInterface::class,
                new SwooleServerConfig(Mode::Base),
            ],
            [
                ServerInterface::class,
                $server,
            ],
        ]);
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new SwooleServerConfig(Mode::Base),
        ]);
        $main = new MainProcess($this->createStub(StdoutLoggerInterface::class), new SwooleConfig());
        new MainProcessCreatedListener($container, $config)->process(
            new MainProcessCreated($main),
        );
        $this->expectException(SupervisorException::class);
        $this->expectExceptionMessageIsOrContains('exit code 1');
        $main->start();
    }


    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testNativeAttachmentExceptionPropagatesAndPreventsStartup(): void
    {
        $failure = new RuntimeException('native attachment failed');
        $server = new UserProcessServer($failure);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new ConfiguredUserProcess('/unused'));
        $provider = new ServerProvider(
            new SwooleServerConfig(Mode::Base),
            new ServerContainer($server),
            new RecordingEventDispatcher(),
            new RecordingStdoutLogger(),
            $this->prepareManager([
                ConfiguredUserProcess::class => [
                    new UserProcess('worker'),
                ],
            ], $container),
        );
        try {
            $provider->start();
            self::fail('Native failures must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertNull($server->countAtStart);
        self::assertCount(1, $server->processes);
        $this->expectException(ServerStartException::class);
        $provider->start();
    }

    /**
     * @param array<class-string<UserProcessInterface>, non-empty-list<UserProcess>> $declarations
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private function prepareManager(array $declarations, ContainerInterface $container): UserProcessManagerInterface
    {
        $metadata = [];
        foreach ($declarations as $class => $attributes) {
            foreach ($attributes as $attribute) {
                $metadata[$class][] = new AttributeMetadata($attribute);
            }
        }
        $annotations = $this->createStub(AnnotationCollectorInterface::class);
        $annotations->method('getClassesWithAttribute')->willReturn($metadata);
        return new UserProcessManagerProvider()($annotations, $container);
    }
}
