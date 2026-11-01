<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Server

`super-kernel/server` constructs and starts one application-configured Swoole service. It provides native and coroutine server construction, the application-facing `ServerInterface`, startup lifecycle events, main Swoole Server callback events, startup logging, and user processes.

It provides no TCP, HTTP, WebSocket, or other protocol handler: protocol components attach their callbacks during Server's per-listener startup event. Shutdown, restart, reload, daemon, PID-file, and signal orchestration are also outside this component.

## Application configuration

Applications mark one implementation of `SuperKernel\Server\Config\SwooleServerConfigInterface` with `#[Configuration]`. The container validates it and supplies that same object as `SwooleServerConfigInterface`. The first listener is the ordinary main listener. It must have a protocol capability at least as high as every additional listener under `WebSocket > Http > Raw`; additional native listeners are created in declared order.

```php
use SuperKernel\Attribute\Configuration;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;
use SuperKernel\Server\Constants\Protocol;

#[Configuration(SwooleServerConfigInterface::class)]
final readonly class ApplicationSwooleServerConfig implements SwooleServerConfigInterface
{
    public function enable(): bool
    {
        return true;
    }

    public function mode(): Mode
    {
        return Mode::Process;
    }

    public function listeners(): array
    {
        return [
            [
                'name' => 'http',
                'host' => '0.0.0.0',
                'port' => 9501,
                'protocol' => Protocol::Http,
            ],
            [
                'name' => 'admin',
                'host' => '0.0.0.0',
                'port' => 9502,
            ],
        ];
    }

    public function settings(): array
    {
        return ['worker_num' => 4];
    }
}
```

`listeners()` returns a non-empty list of arrays described by the interface's PHPDoc shape. Each listener requires non-empty string `name` and `host` fields and an integer `port`. Optional fields are integer `socketType`, boolean `ssl` and `reusePort`, and `Protocol`-typed `protocol`. Omitted options default to `SWOOLE_SOCK_TCP`, `false`, `false`, and `Protocol::Raw` when they are consumed; validation does not add default fields to the source arrays. Unknown keys and explicitly supplied invalid values, including `null`, are rejected. Server settings use string keys; native Swoole option validation and startup failures come from Swoole itself.

Native assembly and lifecycle events use the validated listener arrays unchanged, preserving their fields and order. Configuration getters must remain stable during the runtime: validation does not copy or freeze the application object. Use `#[Configuration]` to customize values; a higher-priority DI `Provider` override bypasses validation.

Implement `enable(): bool` on every configuration source. Without a declaration, automatic mounting is skipped without resolving the interface. A declared configuration is validated before the enable check, including disabled configurations, and loading and validation failures propagate. Returning `false` disables automatic mounting without changing explicit `ServerInterface::start()` calls.

Listener names are non-empty, case-sensitive, unique application identities. Native modes encode TLS in the socket type. Coroutine mode supports exactly one raw listener and consumes that listener's `ssl` and `reusePort` fields. The sole coroutine listener is treated as the logical main service; Server does not invent a default `handle()` callback for it.

## Native servers

For base, process, and supported thread modes, the container supplies `Swoole\Server`, choosing `Swoole\Server`, `Swoole\Http\Server`, or `Swoole\WebSocket\Server` from the primary protocol. It applies settings, adds extra ports, and registers Server's main callback events and startup logger. Coroutine mode supplies `Swoole\Coroutine\Server` instead and rejects ordinary modes.

Main callbacks and startup logging are registered only when the container builds `Swoole\Server` through Server's own provider. An application-owned DI provider for `Swoole\Server` takes precedence and deliberately bypasses that behavior.

## ServerInterface and startup

`SuperKernel\Server\Contract\ServerInterface` resolves the exact retained `Swoole\Server` or `Swoole\Coroutine\Server` selected by configuration. `getServer()` exposes that native object for low-level integration, while `start()` is the canonical application boundary:

```php
use SuperKernel\Server\Contract\ServerInterface;

$container->get(ServerInterface::class)->start();
```

A `ServerInterface` instance accepts one startup attempt. Before entering the native runtime it synchronously dispatches `Event\AfterMainServerCreated` once, then `Event\AfterServerCreated` once for every configured listener in order, including the main listener. The latter exposes the exact native server or port and its original listener array through `configuration(): array`, using the `ListenerConfiguration` array shape documented on `SwooleServerConfigInterface`. Read listener names through `configuration()['name']` and use `['protocol'] ?? Protocol::Raw` for an optional protocol. A listener failure prevents native startup.

After all creation events, non-coroutine servers reset `daemonize=false` immediately before native `start()`. This overrides configuration and creation-listener changes; a rejected setting prevents startup.

Ordinary false starts become `ServerStartException`. Coroutine startup enters `Swoole\Coroutine\run()` only when needed, preserves inner failures, remains blocking to the caller, and logs startup only after the native coroutine server yields. A missing coroutine protocol handler is not hidden by Server.

## Main callbacks and startup logging

Server registers every native main-server callback slot and dispatches a matching `Event\Callbacks\On*` event through `Psr\EventDispatcher\EventDispatcherInterface`: `OnStart`, `OnShutdown`, `OnBeforeShutdown`, `OnManagerStart`, `OnManagerStop`, `OnWorkerStart`, `OnWorkerStop`, `OnWorkerExit`, `OnWorkerError`, `OnTask`, `OnFinish`, `OnPipeMessage`, `OnBeforeReload`, and `OnAfterReload`. Listener failures propagate to the native callback caller. An `OnTask` listener can assign `OnTask::$result`; that value is returned to Swoole. Callback registration failures are logged and raised as `ServerCreationException`. Port callbacks such as `receive`, `request`, and WebSocket message handling belong to protocol components.

On real startup, Server writes one mode notice, one process-ID record, and one listener record per configuration through `StdoutLoggerInterface`. Native modes log from Swoole's `start` callback, after `OnStart` dispatch. Coroutine mode logs only after `start()` yields.

## Running under the main process

This package provides no console command; `swoole:start` belongs to [`super-kernel/swoole`](https://github.com/super-kernel/swoole/blob/main/README.md). On `SuperKernel\Swoole\Event\MainProcessCreated`, Server checks its own configuration. A missing or disabled configuration mounts nothing, and command help and unrelated commands never load it. An enabled configuration mounts one child with `MainProcess::addProcess()`, which resolves `ServerInterface` and calls `start()`; native construction is deferred until that child runs.

Server can run concurrently with a separate Pool child beneath the main process. Direct non-coroutine startup forces foreground execution. Swoole's main process owns optional daemonization and the main PID file, which records the final main-process PID rather than a Server PID. On SIGTERM or SIGINT it sends SIGTERM to mounted children in order and waits for their exit. Server owns its descendant workers.

## User processes

Mark a class with `#[SuperKernel\Server\Attribute\UserProcess]` and implement `Contract\UserProcessInterface`. The Attribute accepts `?string $name = null`, `int $workNums = 1` and `bool $enableCoroutine = false`. A null name uses the attributed class's fully qualified name, even if DI returns another implementation. Counts must be in 0-1024, and explicit names must not be blank. For example, `#[UserProcess('jobs', workNums: 2, enableCoroutine: true)]` declares two coroutine user workers.

The task's readable `enabled` hook controls registration. Its native option hooks are `redirectStdinStdout`, `pipeType` and `enableCoroutine`; the Attribute's coroutine setting overrides the task's preference without mutating the task. `execute(Swoole\Process $process, Swoole\Server $server): void` receives the native user worker and its owning Server. Keep construction and property reads free of connections, reactors and other business resources; initialize them inside `execute()` after the native Server forks.

The container supplies `Contract\UserProcessManagerInterface` with every `#[UserProcess]` class registered in discovery order. Classes that do not implement `UserProcessInterface` are rejected, including disabled or zero-count declarations. Each class with nonzero `workNums` is resolved once; zero-count declarations are not resolved, and disabled tasks add no workers and their native option hooks are not read.

The manager follows Process's registration and query API:

- `add($process, $enableCoroutine = null, $name = null)` registers one task and returns the manager.
- `addBatch($workerNum, $process, $enableCoroutine = null, $name = null)` registers 0-1024 entries atomically. A single worker keeps its name; multiple workers use `name:0`, `name:1`, etc. Unnamed direct registrations use zero-based registration indexes. Names are case-sensitive, must not be blank, and may be full class names. The registry holds at most 1024 workers; a collision or invalid option leaves the batch unregistered.
- `all()` returns effective `UserProcessInterface` tasks in registration order, keyed by expanded name.
- `has($name)` checks exact registration. `get($name)` returns the same effective task as `all()` and throws `Exception\UserProcessNotFoundException` when the name is absent.

Register a `UserProcessInterface` or a `callable(Swoole\Process, Swoole\Server): void`. A null coroutine override inherits the task's preference. Callables default to no redirection, a datagram pipe and coroutine execution disabled; wrap one in `Definition\UserProcessDefinition` to supply different native options. Registration validates pipe types and captures options in immutable effective tasks without changing the original implementation. Repeated `all()`, `has()` and `get()` calls do not resolve services, reread options or allocate native processes.

Complete registration before Server attaches its workers. After the creation events and the `daemonize=false` reset, and before native startup, Server creates one native `Swoole\Process` per registered task in order and attaches it with `Server::addProcess()`; registrations made during creation events are included. Names are registration identities, not OS process titles. The manager has no `start()` or `stop()` operation and owns no Pool configuration or IPC settings. Adding entries after attachment changes only the local registry and does not mount them into a running Server.

Native Server owns worker startup, reaping and restarting. Returning from `execute()` can cause a native restart. No background READY protocol or restart policy is applied. Pure coroutine Servers do not attach user workers. Mode support and registration failures come from the native API without additional checks or wrapping; see the [Swoole 6.2.3 native implementation](https://github.com/swoole/swoole-src/blob/v6.2.3/ext-src/swoole_server.cc).
