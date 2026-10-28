<?php

declare(strict_types=1);

namespace SuperKernel\Server\Provider;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Contract\ServerInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Enum\Protocol;
use SuperKernel\Server\Event\AfterMainServerCreated;
use SuperKernel\Server\Event\AfterServerCreated;
use SuperKernel\Server\Exception\ServerStartException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;
use Throwable;

use function count;
use function get_debug_type;
use function getmypid;
use function sprintf;
use function Swoole\Coroutine\run;

/**
 * @psalm-import-type ListenerConfiguration from SwooleServerConfigInterface
 */
#[Provider(ServerInterface::class)]
final class ServerProvider implements ServerInterface
{
    private bool $startAttempted = false;

    private Server|CoroutineServer|null $server = null;

    public function __construct(
        private readonly SwooleServerConfigInterface $configuration,
        private readonly ContainerInterface          $container,
        private readonly EventDispatcherInterface    $eventDispatcher,
        private readonly StdoutLoggerInterface       $logger,
        private readonly UserProcessManagerInterface $userProcesses,
    )
    {
    }

    public function getServer(): Server|CoroutineServer
    {
        if ($this->server instanceof Server || $this->server instanceof CoroutineServer) {
            return $this->server;
        }

        $serverClass = Mode::Coroutine === $this->configuration->mode()
            ? CoroutineServer::class
            : Server::class;

        try {
            $server = $this->container->get($serverClass);
        } catch (Throwable $throwable) {
            throw new ServerStartException(sprintf(
                'Unable to resolve the configured Swoole service from container entry %s.',
                $serverClass,
            ), 0, $throwable);
        }

        if (!$server instanceof $serverClass) {
            throw new ServerStartException(sprintf(
                'Container entry %s must resolve to that native Swoole service; %s provided.',
                $serverClass,
                get_debug_type($server),
            ));
        }

        $this->server = $server;

        return $server;
    }

    public function start(): void
    {
        if ($this->startAttempted) {
            throw new ServerStartException('The configured Swoole service may only be started once.');
        }

        $this->startAttempted = true;
        $server = $this->getServer();
        $listeners = $this->configuration->listeners();
        $nativeServers = $this->nativeServers($server, $listeners);

        $this->eventDispatcher->dispatch(new AfterMainServerCreated($server));

        foreach ($nativeServers as $index => $nativeServer) {
            $this->eventDispatcher->dispatch(new AfterServerCreated(
                $nativeServer,
                $listeners[$index],
            ));
        }

        if ($server instanceof CoroutineServer) {
            $this->startCoroutineServer($server, $listeners);

            return;
        }

        if (!$server->set([
            'daemonize' => false,
        ])) {
            throw new ServerStartException('Unable to disable Swoole server daemonization before startup.');
        }

        foreach ($this->userProcesses->all() as $task) {
            $server->addProcess(new Process(
                static function (Process $process) use ($task, $server): void {
                    $task->execute($process, $server);
                },
                $task->redirectStdinStdout,
                $task->pipeType,
                $task->enableCoroutine,
            ));
        }

        if (!$server->start()) {
            throw new ServerStartException('The Swoole server failed to start.');
        }
    }

    /**
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @return non-empty-list<Server|ServerPort|CoroutineServer>
     */
    private function nativeServers(Server|CoroutineServer $server, array $listeners): array
    {
        if ($server instanceof CoroutineServer) {
            return [
                $server,
            ];
        }

        $nativeServers = [
            $server,
        ];

        for ($index = 1, $count = count($listeners); $index < $count; ++$index) {
            $port = $server->ports[$index] ?? null;

            if (!$port instanceof ServerPort) {
                throw new ServerStartException(sprintf(
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
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @throws Throwable When the coroutine runtime or startup logging fails
     */
    private function startCoroutineServer(CoroutineServer $server, array $listeners): void
    {
        if (0 <= Coroutine::getCid()) {
            $this->startCoroutineRuntime($server, $listeners);

            return;
        }

        $failure = null;

        run(function () use ($server, $listeners, &$failure): void {
            try {
                $this->startCoroutineRuntime($server, $listeners);
            } catch (Throwable $throwable) {
                $failure = $throwable;
            }
        });

        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }

    /**
     * @param non-empty-list<ListenerConfiguration> $listeners
     *
     * @throws ServerStartException When coroutine creation, result delivery, or startup is rejected
     * @throws Throwable            When native coroutine startup or startup logging throws
     */
    private function startCoroutineRuntime(CoroutineServer $server, array $listeners): void
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
                $this->logStarted($listeners, [
                    $server,
                ]);
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

    /**
     * @param non-empty-list<ListenerConfiguration>             $listeners
     * @param non-empty-list<Server|ServerPort|CoroutineServer> $nativeServers
     */
    private function logStarted(array $listeners, array $nativeServers): void
    {
        $this->logger->notice(sprintf('Swoole main server started in %s mode.', Mode::Coroutine->value));

        $processId = getmypid();

        $this->logger->info(sprintf(
            'Swoole server process started with PID %s.',
            false === $processId ? '-' : $processId,
        ));

        foreach ($listeners as $index => $listener) {
            $nativeServer = $nativeServers[$index];

            $this->logger->info(sprintf(
                'Swoole listener "%s" started on %s:%d (%s).',
                $listener['name'],
                $nativeServer->host,
                $nativeServer->port,
                ($listener['protocol'] ?? Protocol::Raw)->value,
            ));
        }
    }
}
