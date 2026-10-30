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
use SuperKernel\Server\Event\AfterMainServerCreated;
use SuperKernel\Server\Event\AfterServerCreated;
use SuperKernel\Server\Exception\ServerStartException;
use SuperKernel\Server\Startup\CoroutineServerStartup;
use SuperKernel\Server\Startup\ServerEntryResolver;
use Swoole\Coroutine;
use Swoole\Coroutine\Server as CoroutineServer;
use Swoole\Process;
use Swoole\Server;
use Swoole\Server\Port as ServerPort;
use Throwable;

use function count;
use function sprintf;
use function Swoole\Coroutine\run;

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
        return $this->server ??= ServerEntryResolver::resolve($this->configuration, $this->container);
    }

    public function start(): void
    {
        if ($this->startAttempted) {
            throw new ServerStartException('The configured Swoole service may only be started once.');
        }

        $this->startAttempted = true;
        $server = $this->server ??= ServerEntryResolver::resolve($this->configuration, $this->container);
        $listeners = $this->configuration->listeners();
        $nativeServers = [
            $server,
        ];

        if (!$server instanceof CoroutineServer) {
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
        }

        $this->eventDispatcher->dispatch(new AfterMainServerCreated($server));

        foreach ($nativeServers as $index => $nativeServer) {
            $this->eventDispatcher->dispatch(new AfterServerCreated(
                $nativeServer,
                $listeners[$index],
            ));
        }

        if ($server instanceof CoroutineServer) {
            if (0 <= Coroutine::getCid()) {
                CoroutineServerStartup::start($server, $listeners, $this->logger);

                return;
            }

            $failure = null;

            run(function () use ($server, $listeners, &$failure): void {
                try {
                    CoroutineServerStartup::start($server, $listeners, $this->logger);
                } catch (Throwable $throwable) {
                    $failure = $throwable;
                }
            });

            if ($failure instanceof Throwable) {
                throw $failure;
            }

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
}
