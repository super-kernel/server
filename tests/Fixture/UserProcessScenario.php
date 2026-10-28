<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Psr\Container\ContainerInterface;
use RuntimeException;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Event\AfterMainServerCreated;
use SuperKernel\Server\Listener\MainProcessCreatedListener;
use SuperKernel\Server\Manager\UserProcessManager;
use SuperKernel\Server\Provider\ServerProvider;
use SuperKernel\Server\Provider\SwooleServerConfigProvider;
use SuperKernel\Swoole\Event\MainProcessCreated;
use SuperKernel\Swoole\MainProcess;
use Swoole\Server;
use Swoole\Timer;
use Throwable;

use function count;
use function file;
use function file_exists;
use function file_put_contents;
use function getmypid;
use function json_encode;
use function microtime;
use function pcntl_waitpid;

use const JSON_THROW_ON_ERROR;
use const SWOOLE_BASE;
use const SWOOLE_PROCESS;
use const WNOHANG;

/** Runs a real Server and registered user workers on a fixture-owned ephemeral loopback port. */
final class UserProcessScenario
{
    /** @throws Throwable when configuration, native startup or fixture recording fails */
    public static function run(
        string $directory,
        string $mode,
        int    $pipeType,
        bool   $redirect,
        bool   $coroutine,
        bool   $restart,
        bool   $callable = false,
    ): void
    {
        $workerLog = $directory . '/workers.jsonl';
        $config = new ConfigurationRegistry([
            SwooleServerConfigInterface::class => new SwooleServerConfig(Mode::from($mode), [
                [
                    'name' => 'fixture',
                    'host' => '127.0.0.1',
                    'port' => 0,
                ],
            ]),
        ]);
        $task = new ConfiguredUserProcess($workerLog, $pipeType, $redirect, $coroutine, $restart);
        $configuration = new SwooleServerConfigProvider()($config);
        $container = new class($mode, $directory, $workerLog, $task, $configuration) implements ContainerInterface {
            public ?ServerProvider $provider = null;
            public ?Server $native = null;

            public function __construct(
                private readonly string                      $mode,
                private readonly string                      $directory,
                private readonly string                      $workerLog,
                private readonly ConfiguredUserProcess       $task,
                private readonly SwooleServerConfigInterface $configuration,
            )
            {
            }

            public function get(string $id): mixed
            {
                if (SwooleServerConfigInterface::class === $id) {
                    return $this->configuration;
                }
                if (ServerInterface::class === $id) {
                    return $this->provider;
                }
                if (ConfiguredUserProcess::class === $id) {
                    return $this->task;
                }
                if (Server::class !== $id) {
                    throw new RuntimeException('Unexpected fixture container entry: ' . $id);
                }
                if (null !== $this->native) {
                    return $this->native;
                }
                $server = new Server('127.0.0.1', 0, 'base' === $this->mode ? SWOOLE_BASE : SWOOLE_PROCESS);
                $server->set([
                    'worker_num'    => 1,
                    'reactor_num'   => 1,
                    'max_wait_time' => 1,
                    'log_file'      => $this->directory . '/native.log',
                ]);
                $server->on('Receive', static function (): void {
                });
                $server->on('Start', function (Server $server): void {
                    $deadline = microtime(true) + 3;
                    Timer::tick(10, function (int $timer) use ($server, $deadline): void {
                        $complete = file_exists($this->workerLog) && 2 <= count(file($this->workerLog));
                        if ($complete || microtime(true) >= $deadline) {
                            Timer::clear($timer);
                            $server->shutdown();
                        }
                    });
                });
                return $this->native = $server;
            }

            public function has(string $id): bool
            {
                return SwooleServerConfigInterface::class === $id
                    || ServerInterface::class === $id
                    || Server::class === $id
                    || ConfiguredUserProcess::class === $id;
            }
        };
        $manager = new UserProcessManager();
        $manager->addBatch($restart ? 1 : 2, $callable ? $task->execute(...) : $task, $coroutine, 'fixture');
        $mainListener = new MainProcessCreatedListener($container, $config);
        $dispatcher = new RecordingEventDispatcher(static function (object $event) use (
            $mainListener,
            $task,
        ): void {
            if ($event instanceof MainProcessCreated) {
                $mainListener->process($event);
            } elseif ($event instanceof AfterMainServerCreated) {
                $task->expectedServer = $event->server();
            }
        });
        $container->provider = new ServerProvider(
            $configuration,
            $container,
            $dispatcher,
            new RecordingStdoutLogger(),
            $manager,
        );
        $main = new MainProcess(new RecordingStdoutLogger(), new SwooleConfig());
        $dispatcher->dispatch(
            new MainProcessCreated($main),
        );
        if (null !== $container->native || file_exists($workerLog)) {
            throw new RuntimeException('Server startup must be deferred until MainProcess::start().');
        }
        $main->start();
        $status = 0;
        file_put_contents($directory . '/result.json', json_encode([
            'pid'            => getmypid(),
            'remainingChild' => pcntl_waitpid(-1, $status, WNOHANG),
        ], JSON_THROW_ON_ERROR));
    }
}
