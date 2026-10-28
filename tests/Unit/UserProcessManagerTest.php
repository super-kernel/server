<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Definition\UserProcessDefinition;
use SuperKernel\Server\Exception\UserProcessNotFoundException;
use SuperKernel\Server\Manager\UserProcessManager;
use SuperKernel\Server\Tests\Fixture\ConfiguredUserProcess;
use SuperKernel\Server\Tests\Fixture\NativeServer;
use Swoole\Process;
use Swoole\Server;
use Throwable;

use function array_keys;
use function array_map;
use function str_repeat;

final class UserProcessManagerTest extends TestCase
{
    public function testContractOnlyExposesRegistrationAndQueries(): void
    {
        $contract = new ReflectionClass(UserProcessManagerInterface::class);
        self::assertSame([
            'add',
            'addBatch',
            'all',
            'has',
            'get',
        ], array_map(static fn(ReflectionMethod $method): string => $method->getName(), $contract->getMethods()));
        self::assertFalse(new ReflectionClass(UserProcessManager::class)->hasMethod('start'));
    }

    /**
     * The callback retains the signature required by its registration or reflection contract.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testNamedQueriesRetainEffectiveTaskIdentityAndRegistrationOrder(): void
    {
        $first = new UserProcessDefinition(
            static function (Process $process, Server $server): void {
                self::fail('Registration must not execute a task.');
            },
        );
        $second = new UserProcessDefinition(
            static function (Process $process, Server $server): void {
                self::fail('Registration must not execute a task.');
            },
        );
        $manager = new UserProcessManager();
        self::assertSame($manager, $manager->add($first, name: '0')->add($second, name: '00'));
        self::assertSame([
            '0'  => $first,
            '00' => $second,
        ], $manager->all());
        self::assertTrue($manager->has('0'));
        self::assertSame($first, $manager->get('0'));
        self::assertSame($second, $manager->get('00'));
        self::assertFalse($manager->has('missing'));
        $this->expectException(UserProcessNotFoundException::class);
        $this->expectExceptionMessageIs('User process not found: "missing".');
        $manager->get('missing');
    }

    /**
     * The callback retains the signature required by its registration or reflection contract.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testBatchNamesCallableDefaultsAndCoroutineOverrides(): void
    {
        $callback = static function (Process $process, Server $server): void {
            self::fail('Registration must not execute a callback.');
        };
        $manager = new UserProcessManager();
        self::assertSame($manager, $manager->add($callback)->addBatch(2, $callback, true, 'jobs'));
        self::assertSame([
            0,
            'jobs:0',
            'jobs:1',
        ], array_keys($manager->all()));
        self::assertFalse($manager->get('0')->enableCoroutine);
        self::assertFalse($manager->get('0')->redirectStdinStdout);
        self::assertSame(Process::PIPE_TYPE_DGRAM, $manager->get('0')->pipeType);
        self::assertTrue($manager->get('jobs:0')->enableCoroutine);
        self::assertSame($manager->get('jobs:0'), $manager->get('jobs:1'));
        $task = new UserProcessDefinition($callback, true, Process::PIPE_TYPE_STREAM, true);
        $manager->add($task, name: 'inherited')->add($task, false, 'overridden');
        self::assertSame($task, $manager->get('inherited'));
        self::assertNotSame($task, $manager->get('overridden'));
        self::assertFalse($manager->get('overridden')->enableCoroutine);
        self::assertTrue($manager->get('overridden')->redirectStdinStdout);
        self::assertSame(Process::PIPE_TYPE_STREAM, $manager->get('overridden')->pipeType);
        self::assertTrue($task->enableCoroutine);
    }

    public function testNativeOptionsAreCapturedOnceDuringDirectRegistration(): void
    {
        $task = new ConfiguredUserProcess('/unused', Process::PIPE_TYPE_STREAM, true, true);
        $manager = new UserProcessManager();
        $manager->addBatch(2, $task, name: 'workers');
        $registered = $manager->get('workers:0');
        self::assertNotSame($task, $registered);
        $task->pipeType = 42;
        $task->redirectStdinStdout = false;
        $task->enableCoroutine = false;
        $task->enabled = false;

        self::assertSame($registered, $manager->get('workers:0'));
        self::assertSame($registered, $manager->get('workers:1'));
        self::assertSame(Process::PIPE_TYPE_STREAM, $registered->pipeType);
        self::assertTrue($registered->redirectStdinStdout);
        self::assertTrue($registered->enableCoroutine);
        self::assertTrue($registered->enabled);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testAdaptedCallbacksReceiveTheExactNativeProcessAndServer(): void
    {
        $calls = [];
        $callback = static function (Process $process, Server $server) use (&$calls): void {
            $calls[] = [
                $process,
                $server,
            ];
        };
        $manager = new UserProcessManager();
        $manager->add($callback, name: 'callable');
        $manager->add(new UserProcessDefinition($callback, enableCoroutine: true), false, 'overridden');
        self::assertSame([], $calls);
        $process = new Process(static function (): void {
        }, false, Process::PIPE_TYPE_NONE);
        $server = new NativeServer();
        $manager->get('callable')->execute($process, $server);
        $manager->get('overridden')->execute($process, $server);

        self::assertSame([
            [
                $process,
                $server,
            ],
            [
                $process,
                $server,
            ],
        ], $calls);
    }

    /**
     * The callback retains the signature required by its registration or reflection contract.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testTaskFailuresPropagateThroughTheAdapterUnchanged(): void
    {
        $failure = new RuntimeException('User task failed.');
        $manager = new UserProcessManager();
        $manager->add(
            static function (Process $process, Server $server) use ($failure): void {
                throw $failure;
            },
        );
        $process = new Process(static function (): void {
        }, false, Process::PIPE_TYPE_NONE);

        try {
            $manager->get('0')->execute($process, new NativeServer());
            self::fail('Task failures must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testBatchCollisionDoesNotPartiallyRegisterOrReplaceTasks(): void
    {
        $task = new UserProcessDefinition(static function (Process $process, Server $server): void {
        });
        $manager = new UserProcessManager();
        $manager->add($task, name: 'jobs:1');
        $this->expectException(LogicException::class);
        try {
            $manager->addBatch(2, $task, name: 'jobs');
        } finally {
            self::assertSame([
                'jobs:1' => $task,
            ], $manager->all());
        }
    }

    public function testDisabledAndZeroCountTasksReserveNoNamesOrCapacity(): void
    {
        $callback = static function (Process $process, Server $server): void {
        };
        $disabled = new UserProcessDefinition($callback, pipeType: 42, enabled: false);
        $manager = new UserProcessManager();
        self::assertSame($manager, $manager->add($disabled, true, 'active'));
        self::assertSame($manager, $manager->addBatch(1024, $disabled));
        self::assertSame($manager, $manager->addBatch(0, new UserProcessDefinition($callback, pipeType: 42)));
        self::assertSame([], $manager->all());
        $manager->add($callback, name: 'active')->add($callback);

        self::assertFalse($manager->has('0'));
        self::assertTrue($manager->has('1'));
        self::assertCount(2, $manager->all());
        self::assertTrue($manager->get('active')->enabled);
    }

    public function testFullClassNamesAndCaseArePreserved(): void
    {
        $name = str_repeat('Namespace\\', 12) . 'UserTask';
        $task = new UserProcessDefinition(static function (Process $process, Server $server): void {
        });
        $manager = new UserProcessManager();
        $manager->addBatch(2, $task, name: $name)->add($task, name: 'Job')->add($task, name: 'job');

        self::assertSame([
            $name . ':0' => $task,
            $name . ':1' => $task,
            'Job'        => $task,
            'job'        => $task,
        ], $manager->all());
    }

    /** @return iterable<string, array{int}> */
    public static function invalidCounts(): iterable
    {
        yield 'negative' => [
            -1,
        ];
        yield 'excessive' => [
            1025,
        ];
    }

    #[DataProvider('invalidCounts')]
    public function testRejectsInvalidCountsWithoutRegisteringWorkers(int $workerNum): void
    {
        $manager = new UserProcessManager();
        $this->expectException(InvalidArgumentException::class);
        try {
            $manager->addBatch($workerNum, static function (Process $process, Server $server): void {
            });
        } finally {
            self::assertSame([], $manager->all());
        }
    }

    public function testTotalCapacityIsEnforcedAtomically(): void
    {
        $callback = static function (Process $process, Server $server): void {
        };
        $manager = new UserProcessManager();
        $manager->addBatch(1024, $callback);
        $before = $manager->all();
        self::assertCount(1024, $before);
        self::assertTrue($manager->has('1023'));
        $this->expectException(LogicException::class);
        try {
            $manager->add($callback);
        } finally {
            self::assertSame($before, $manager->all());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function blankNames(): iterable
    {
        yield 'empty' => [
            '',
        ];
        yield 'whitespace' => [
            ' ',
        ];
    }

    #[DataProvider('blankNames')]
    public function testBlankNamesAreRejected(string $name): void
    {
        $manager = new UserProcessManager();
        $this->expectException(LogicException::class);
        $manager->addBatch(2, static function (Process $process, Server $server): void {
        }, name: $name);
    }

    public function testInvalidPipeTypeDoesNotLeavePartOfABatchRegistered(): void
    {
        $task = new ConfiguredUserProcess('/unused', pipeType: 42);
        $manager = new UserProcessManager();
        $this->expectException(InvalidArgumentException::class);
        try {
            $manager->addBatch(2, $task, name: 'jobs');
        } finally {
            self::assertSame([], $manager->all());
        }
    }
}
