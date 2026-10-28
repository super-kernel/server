<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Server\Attribute\UserProcess;
use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Provider\UserProcessManagerProvider;
use SuperKernel\Server\Tests\Fixture\ConfiguredUserProcess;
use SuperKernel\Server\Tests\Fixture\InvalidUserProcess;
use Swoole\Process;
use Swoole\Server;
use Throwable;

use function array_keys;

final class UserProcessManagerProviderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testFactoryValidatesOnceAndRepeatedReadsPreserveSnapshots(): void
    {
        $binding = new ReflectionClass(UserProcessManagerProvider::class)
            ->getAttributes(FactoryProvider::class)[0]->newInstance();
        self::assertSame(UserProcessManagerInterface::class, $binding->className);
        $enabled = new UserProcess('enabled', workNums: 2, enableCoroutine: true);
        $zero = new UserProcess('zero', workNums: 0);
        $annotations = $this->createMock(AnnotationCollectorInterface::class);
        $annotations->expects(self::once())->method('getClassesWithAttribute')->with(UserProcess::class)->willReturn([
            ConfiguredUserProcess::class => [
                new AttributeMetadata($enabled),
                new AttributeMetadata($zero),
            ],
        ]);

        $task = new ConfiguredUserProcess('/unused');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ConfiguredUserProcess::class)->willReturn($task);
        $manager = new UserProcessManagerProvider()($annotations, $container);
        $processes = $manager->all();
        self::assertSame([
            'enabled:0',
            'enabled:1',
        ], array_keys($processes));
        self::assertInstanceOf(UserProcessInterface::class, $processes['enabled:0']);
        self::assertSame($processes['enabled:0'], $processes['enabled:1']);
        self::assertSame(Process::PIPE_TYPE_DGRAM, $processes['enabled:0']->pipeType);
        self::assertFalse($processes['enabled:0']->redirectStdinStdout);
        self::assertTrue($processes['enabled:0']->enableCoroutine);
        self::assertTrue($processes['enabled:1']->enableCoroutine);
        self::assertFalse($task->enableCoroutine);
        $task->pipeType = 42;
        $task->redirectStdinStdout = true;
        $task->enabled = false;
        self::assertSame($processes, $manager->all());
        self::assertSame($processes, $manager->all());
        self::assertSame(Process::PIPE_TYPE_DGRAM, $processes['enabled:0']->pipeType);
        self::assertFalse($processes['enabled:0']->redirectStdinStdout);
        self::assertTrue($processes['enabled:0']->enableCoroutine);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testGetsPreparedProcessesByExactExpandedNameWithoutResolvingAgain(): void
    {
        $task = new ConfiguredUserProcess('/unused');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ConfiguredUserProcess::class)->willReturn($task);
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('single'),
                new UserProcess('workers', workNums: 2),
                new UserProcess('zero', workNums: 0),
            ],
        ], $container);

        $processes = $manager->all();
        self::assertSame($processes['single'], $manager->get('single'));
        self::assertSame($processes['workers:0'], $manager->get('workers:0'));
        self::assertSame($processes['workers:0'], $manager->get('workers:1'));
        $task->pipeType = 42;
        self::assertSame($processes['single'], $manager->get('single'));
        self::assertFalse($manager->has('single:0'));
        self::assertFalse($manager->has('workers'));
        self::assertFalse($manager->has('workers:2'));
        self::assertFalse($manager->has('zero'));
        self::assertFalse($manager->has('missing'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testNullNamesUseTheAttributedClassWithBatchSuffixes(): void
    {
        $attribute = new ReflectionClass(ConfiguredUserProcess::class)
            ->getAttributes(UserProcess::class)[0]->newInstance();
        self::assertNull($attribute->name);
        self::assertSame(1, $attribute->workNums);
        self::assertFalse($attribute->enableCoroutine);
        $task = new class() implements UserProcessInterface {
            public bool $enabled = true;
            public bool $redirectStdinStdout = false;
            public int $pipeType = Process::PIPE_TYPE_DGRAM;
            public bool $enableCoroutine = false;

            public function execute(Process $process, Server $server): void
            {
                TestCase::fail('Collection must not execute a task.');
            }
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturn($task);
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                $attribute,
            ],
            $task::class => [
                new UserProcess(workNums: 2),
            ],
        ], $container);

        self::assertSame([
            ConfiguredUserProcess::class,
            $task::class . ':0',
            $task::class . ':1',
        ], array_keys($manager->all()));
        self::assertSame($manager->all()[ConfiguredUserProcess::class], $manager->get(ConfiguredUserProcess::class));
        self::assertSame($manager->all()[$task::class . ':0'], $manager->get($task::class . ':0'));
        self::assertSame($manager->get($task::class . ':0'), $manager->get($task::class . ':1'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDefaultCoroutineSettingOverridesTheTaskPreference(): void
    {
        $task = new ConfiguredUserProcess('/unused', enableCoroutine: true);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($task);
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('worker'),
            ],
        ], $container);

        self::assertFalse($manager->all()['worker']->enableCoroutine);
        self::assertTrue($task->enableCoroutine);
        self::assertNotSame($task, $manager->get('worker'));
    }

    /** @return iterable<string, array{int}> */
    public static function invalidDeclarations(): iterable
    {
        yield 'nonzero' => [
            1,
        ];
        yield 'zero workers' => [
            0,
        ];
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    #[DataProvider('invalidDeclarations')]
    public function testRejectsInvalidClassesEvenWithZeroWorkers(int $workNums): void
    {
        $annotations = $this->createStub(AnnotationCollectorInterface::class);
        $annotations->method('getClassesWithAttribute')->willReturn([
            InvalidUserProcess::class => [
                new AttributeMetadata(new UserProcess('invalid', $workNums)),
            ],
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            InvalidUserProcess::class . ' must implement ' . UserProcessInterface::class,
        );

        new UserProcessManagerProvider()($annotations, $container);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testEmptyCollectionHasNoDeclarations(): void
    {
        $annotations = $this->createStub(AnnotationCollectorInterface::class);
        $annotations->method('getClassesWithAttribute')->willReturn([]);
        $manager = new UserProcessManagerProvider()(
            $annotations,
            $this->createStub(ContainerInterface::class),
        );
        self::assertSame([], $manager->all());
        self::assertFalse($manager->has('missing'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidMetadata(): iterable
    {
        yield 'empty name' => [
            '',
            1,
        ];
        yield 'blank name' => [
            ' ',
            1,
        ];
        yield 'negative count' => [
            'invalid',
            -1,
        ];
        yield 'excessive count' => [
            'invalid',
            1025,
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(string $name, int $workNums): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserProcess($name, $workNums);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testZeroWorkersDoNotResolveImplementations(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess(workNums: 0),
            ],
        ], $container);
        self::assertSame([], $manager->all());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDisabledTasksAreResolvedOnceWithoutReadingNativeOptions(): void
    {
        $task = new class() implements UserProcessInterface {
            public bool $enabled { get => false; }

            public bool $redirectStdinStdout {
                get {
                    TestCase::fail('Disabled task options must not be read.');
                }
            }

            public int $pipeType {
                get {
                    TestCase::fail('Disabled task options must not be read.');
                }
            }

            public bool $enableCoroutine {
                get {
                    TestCase::fail('Disabled task options must not be read.');
                }
            }

            public function execute(Process $process, Server $server): void
            {
                TestCase::fail('Disabled tasks must not execute.');
            }
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ConfiguredUserProcess::class)->willReturn($task);
        $manager = $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('disabled', workNums: 2, enableCoroutine: true),
                new UserProcess('also-disabled'),
            ],
        ], $container);

        self::assertSame([], $manager->all());
        self::assertFalse($manager->has('disabled:0'));
        self::assertFalse($manager->has('also-disabled'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsInvalidPipeType(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new ConfiguredUserProcess('/unused', pipeType: 42));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid user process pipe type');
        $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('invalid'),
            ],
        ], $container);

    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsCollidingExpandedNames(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new ConfiguredUserProcess('/unused'));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Duplicate user process name: job:1');
        $this->prepareManager([
            ConfiguredUserProcess::class => [
                new UserProcess('job', workNums: 2),
                new UserProcess('job:1'),
            ],
        ], $container);

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
