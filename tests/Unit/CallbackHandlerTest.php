<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;
use SuperKernel\Server\Callbacks\StartCallback;
use SuperKernel\Server\Event\Callbacks\OnAfterReload;
use SuperKernel\Server\Event\Callbacks\OnBeforeReload;
use SuperKernel\Server\Event\Callbacks\OnBeforeShutdown;
use SuperKernel\Server\Event\Callbacks\OnFinish;
use SuperKernel\Server\Event\Callbacks\OnManagerStart;
use SuperKernel\Server\Event\Callbacks\OnManagerStop;
use SuperKernel\Server\Event\Callbacks\OnPipeMessage;
use SuperKernel\Server\Event\Callbacks\OnShutdown;
use SuperKernel\Server\Event\Callbacks\OnStart;
use SuperKernel\Server\Event\Callbacks\OnTask;
use SuperKernel\Server\Event\Callbacks\OnWorkerError;
use SuperKernel\Server\Event\Callbacks\OnWorkerExit;
use SuperKernel\Server\Event\Callbacks\OnWorkerStart;
use SuperKernel\Server\Event\Callbacks\OnWorkerStop;
use SuperKernel\Server\Tests\Fixture\NativeEventServer;
use SuperKernel\Server\Tests\Fixture\RecordingEventDispatcher;
use Swoole\Server;
use Swoole\Server\PipeMessage;
use Swoole\Server\StatusInfo;
use Swoole\Server\Task;
use Swoole\Server\TaskResult;
use Throwable;

use function array_key_last;
use function array_map;
use function in_array;
use function str_replace;

final class CallbackHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, list<string>, string, list<int>}>
     */
    public static function callbackSignatures(): iterable
    {
        yield 'start' => [
            OnStart::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'before shutdown' => [
            OnBeforeShutdown::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'shutdown' => [
            OnShutdown::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'worker start' => [
            OnWorkerStart::class,
            [
                Server::class,
                'int',
            ],
            'void',
            [],
        ];
        yield 'worker stop' => [
            OnWorkerStop::class,
            [
                Server::class,
                'int',
            ],
            'void',
            [],
        ];
        yield 'before reload' => [
            OnBeforeReload::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'after reload' => [
            OnAfterReload::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'task' => [
            OnTask::class,
            [
                Server::class,
                Task::class . '|int',
                '?int',
                'mixed',
            ],
            'mixed',
            [
                2,
                3,
            ],
        ];
        yield 'finish' => [
            OnFinish::class,
            [
                Server::class,
                TaskResult::class . '|int',
                'mixed',
            ],
            'void',
            [
                2,
            ],
        ];
        yield 'worker exit' => [
            OnWorkerExit::class,
            [
                Server::class,
                'int',
            ],
            'void',
            [],
        ];
        yield 'worker error' => [
            OnWorkerError::class,
            [
                Server::class,
                StatusInfo::class . '|int',
                '?int',
                '?int',
                '?int',
            ],
            'void',
            [
                2,
                3,
                4,
            ],
        ];
        yield 'manager start' => [
            OnManagerStart::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'manager stop' => [
            OnManagerStop::class,
            [
                Server::class,
            ],
            'void',
            [],
        ];
        yield 'pipe message' => [
            OnPipeMessage::class,
            [
                Server::class,
                PipeMessage::class . '|int',
                'mixed',
            ],
            'void',
            [
                2,
            ],
        ];
    }

    /**
     * @param class-string $eventClass
     * @param list<string> $parameterTypes
     * @param list<int>    $optionalPositions
     *
     * @throws Throwable When fixture setup or the operation under test throws
     */
    #[DataProvider('callbackSignatures')]
    public function testDeclaresTheEventPayloadAndNativeCallbackHandler(
        string $eventClass,
        array  $parameterTypes,
        string $returnType,
        array  $optionalPositions,
    ): void
    {
        $event = new ReflectionClass($eventClass);
        $constructor = $event->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($event->isFinal());
        self::assertSame(OnTask::class !== $eventClass, $event->isReadOnly());
        self::assertSame($parameterTypes, array_map(
            static fn(ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $constructor->getParameters(),
        ));

        foreach ($constructor->getParameters() as $position => $parameter) {
            self::assertTrue($parameter->isPromoted());
            self::assertTrue($event->getProperty($parameter->getName())->isPublic());
            self::assertTrue($event->getProperty($parameter->getName())->isReadOnly());
            self::assertSame(
                in_array($position, $optionalPositions, true),
                $parameter->isDefaultValueAvailable(),
            );

            if ($parameter->isDefaultValueAvailable()) {
                self::assertNull($parameter->getDefaultValue());
            }
        }

        if (OnTask::class === $eventClass) {
            self::assertTrue($event->hasProperty('result'));
            self::assertFalse($event->getProperty('result')->isReadOnly());
        }

        $handler = new ReflectionClass(self::handlerClass($eventClass));
        $dependencies = $handler->getConstructor()?->getParameters() ?? [];
        $invoke = $handler->getMethod('__invoke');

        self::assertTrue($handler->isFinal());
        self::assertTrue($handler->isReadOnly());
        self::assertFalse($handler->getParentClass());
        self::assertCount(1, $dependencies);
        self::assertSame(EventDispatcherInterface::class, (string) $dependencies[0]->getType());
        self::assertSame($returnType, (string) $invoke->getReturnType());
        self::assertSame($parameterTypes, array_map(
            static fn(ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $invoke->getParameters(),
        ));

        foreach ($invoke->getParameters() as $position => $parameter) {
            self::assertSame(
                in_array($position, $optionalPositions, true),
                $parameter->isDefaultValueAvailable(),
            );

            if ($parameter->isDefaultValueAvailable()) {
                self::assertNull($parameter->getDefaultValue());
            }
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCallbackHandlersDispatchTheirCorrespondingEventsWithCompletePayloads(): void
    {
        $server = new NativeEventServer();
        $dispatcher = new RecordingEventDispatcher(static function (object $event): void {
            if ($event instanceof OnTask) {
                $event->result = 'task result';
            }
        });

        /** @var array<class-string, list<mixed>> $invocations */
        $invocations = [
            OnStart::class => [
                $server,
            ],
            OnBeforeShutdown::class => [
                $server,
            ],
            OnShutdown::class => [
                $server,
            ],
            OnWorkerStart::class => [
                $server,
                1,
            ],
            OnWorkerStop::class => [
                $server,
                2,
            ],
            OnBeforeReload::class => [
                $server,
            ],
            OnAfterReload::class => [
                $server,
            ],
            OnTask::class => [
                $server,
                7,
                3,
                'payload',
            ],
            OnFinish::class => [
                $server,
                7,
                'task result',
            ],
            OnWorkerExit::class => [
                $server,
                4,
            ],
            OnWorkerError::class => [
                $server,
                5,
                1234,
                255,
                15,
            ],
            OnManagerStart::class => [
                $server,
            ],
            OnManagerStop::class => [
                $server,
            ],
            OnPipeMessage::class => [
                $server,
                6,
                'message',
            ],
        ];

        foreach ($invocations as $eventClass => $arguments) {
            $handlerClass = self::handlerClass($eventClass);
            $handler = new $handlerClass($dispatcher);
            $result = $handler(...$arguments);
            $event = $dispatcher->events[array_key_last($dispatcher->events)];
            $constructor = new ReflectionClass($eventClass)->getConstructor();

            self::assertInstanceOf($eventClass, $event);

            foreach ($constructor?->getParameters() ?? [] as $position => $parameter) {
                self::assertSame($arguments[$position], $event->{$parameter->getName()});
            }

            self::assertSame(OnTask::class === $eventClass ? 'task result' : null, $result);
        }

        self::assertCount(14, $dispatcher->events);
    }

    public function testCallbackHandlersPreserveSwooleObjectPayloadForms(): void
    {
        $server = new NativeEventServer();
        $dispatcher = new RecordingEventDispatcher();

        /** @var array<class-string, array{string, object}> $payloads */
        $payloads = [
            OnTask::class => [
                'task',
                new Task(),
            ],
            OnFinish::class => [
                'result',
                new TaskResult(),
            ],
            OnWorkerError::class => [
                'status',
                new StatusInfo(),
            ],
            OnPipeMessage::class => [
                'message',
                new PipeMessage(),
            ],
        ];

        foreach ($payloads as $eventClass => [$property, $payload]) {
            $handlerClass = self::handlerClass($eventClass);
            $handler = new $handlerClass($dispatcher);
            $handler($server, $payload);
            $event = $dispatcher->events[array_key_last($dispatcher->events)];

            self::assertInstanceOf($eventClass, $event);
            self::assertSame($payload, $event->{$property});
        }
    }

    public function testPropagatesCallbackListenerFailures(): void
    {
        $server = new NativeEventServer();
        $failure = new RuntimeException('listener failed');
        $events = new RecordingEventDispatcher(
            static function () use ($failure): never {
                throw $failure;
            },
        );
        $handler = new StartCallback($events);

        try {
            $handler($server);
            self::fail('Expected the listener failure to propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * @param class-string $eventClass
     *
     * @return class-string
     */
    private static function handlerClass(string $eventClass): string
    {
        return str_replace('\\Event\\Callbacks\\On', '\\Callbacks\\', $eventClass) . 'Callback';
    }
}
