# SuperKernel Server

`super-kernel/server` constructs and starts one application-configured Swoole service. It owns native and coroutine
factory providers, the application-facing `ServerInterface`, startup lifecycle events, main Swoole Server callbacks,
startup logging, and the `ServerInterface` startup boundary.

The package owns no TCP, HTTP, WebSocket, or other protocol handler. Protocol components attach their callbacks during
Server's per-listener startup event. Shutdown, restart, reload, daemon, PID-file, and signal orchestration also remain
outside this component.

The package directly requires `super-kernel/swoole` at `dev-main`. Swoole also requires Server, so the two packages
currently have mutual production dependencies.

## Application configuration

Applications ordinarily mark one implementation of
`SuperKernel\Server\Config\SwooleServerConfigInterface` with `#[Configuration]`. Config loads that raw source;
`Provider\SwooleServerConfigProvider` validates it and returns the same object as the DI entry for
`SwooleServerConfigInterface`. Applications supply its configuration source.
The first listener is the ordinary main listener. It must have a protocol capability at
least as high as every additional listener under `WebSocket > Http > Raw`; additional native listeners are created
in declared order.

```php
use SuperKernel\Attribute\Configuration;
use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Enum\Mode;
use SuperKernel\Server\Enum\Protocol;

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

`listeners()` returns a non-empty list of arrays described by the interface's PHPDoc shape. Each listener requires
non-empty string `name` and `host` fields and an integer `port`. Optional fields are integer `socketType`, boolean
`ssl` and `reusePort`, and `Protocol`-typed `protocol`. Omitted options default to `SWOOLE_SOCK_TCP`, `false`, `false`,
and `Protocol::Raw`. Unknown keys and explicitly supplied invalid values, including `null`, are rejected.
Server settings use string keys; native Swoole option validation and startup failures remain native-factory concerns.

The factory targets `SwooleServerConfigInterface::class` and receives `ConfigInterface` through DI.
Server consumers inject that interface. Native assembly and lifecycle events use its validated listener arrays
directly, preserving their fields and order. Missing optional fields use `SWOOLE_SOCK_TCP`, `false` for `ssl` and
`reusePort`, and `Protocol::Raw` when consumed; validation does not add default fields to the source arrays.
Configuration getters must remain stable during the runtime: validation does not copy or freeze the application
object. Use `#[Configuration]` to customize values; a higher-priority DI Provider override bypasses factory validation.

Implement `enable(): bool` on every application configuration source. The main-process listener checks
`ConfigInterface::has(SwooleServerConfigInterface::class)` before resolving the interface through its injected
container. An absent declaration skips mounting without resolving the interface. Declared configuration is
validated before the enable check, including disabled configurations; loading and validation failures propagate.
Returning `false` disables automatic mounting without changing explicit `ServerInterface::start()` calls.

Listener names are non-empty, case-sensitive, unique application identities. Native modes encode TLS in the socket
type. Coroutine mode supports exactly one raw listener and consumes that listener's `ssl` and `reusePort` fields.
The sole coroutine listener is treated as the logical main service; Server does not invent a default `handle()`
callback for it.

## Factory providers

`Provider\SwooleServerProvider` receives the validated `SwooleServerConfigInterface` and provides
`Swoole\Server` for base, process, and supported thread modes. It chooses `Swoole\Server`,
`Swoole\Http\Server`, or
`Swoole\WebSocket\Server` from the primary protocol, applies settings, adds extra ports, then registers Server's main
callback bridge and startup logger before returning the native service.

`Provider\CoroutineServerProvider` separately receives the validated `SwooleServerConfigInterface` and
provides `Swoole\Coroutine\Server`. It rejects ordinary modes; the configuration factory has already guaranteed
exactly one raw listener.

Automatic main callback registration occurs only when `Swoole\Server` is created through `SwooleServerProvider`. An
application-owned DI provider for `Swoole\Server` takes precedence and deliberately bypasses that automatic behavior.
Protocol callbacks never move into either native factory.

## ServerInterface provider and startup

`Provider\ServerProvider` is the `#[Provider(ServerInterface::class)]` implementation. It resolves the exact retained
`Swoole\Server` or `Swoole\Coroutine\Server` selected by configuration. `getServer()` exposes that native object for
low-level integration, while `start()` is the canonical application boundary:

```php
use SuperKernel\Server\Contract\ServerInterface;

$container->get(ServerInterface::class)->start();
```

A provider instance accepts one startup attempt. Before entering the native runtime it synchronously dispatches
`Event\AfterMainServerCreated` once, then `Event\AfterServerCreated` once for every configured listener in order,
including the main listener. The latter exposes the exact native server or port and its original listener array
through `configuration(): array`. The listener creation event uses the `ListenerConfiguration` array shape documented
on `SwooleServerConfigInterface`. Read listener names through
`configuration()['name']` and use `['protocol'] ?? Protocol::Raw` for an optional protocol.
A listener failure prevents native startup.

After all creation events, non-coroutine servers reset `daemonize=false` immediately before native `start()`.
This overrides configuration and creation-listener changes; a rejected setting prevents startup.

Shutdown and signal handling remain outside `ServerInterface`. Applications can define their own event types for
additional lifecycle boundaries; native shutdown callback events retain their own dispatch points.

Ordinary false starts become `ServerStartException`. Coroutine startup enters `Swoole\Coroutine\run()` only when
needed, preserves inner failures, remains blocking to the caller, and logs startup only after the native coroutine
server yields. A missing coroutine protocol handler is not hidden by Server.

## Main callbacks and startup logging

`Provider\SwooleServerProvider` directly resolves the 14 Server-owned
`Callbacks\*Callback` services from DI and exclusively registers each native main-server slot. Handler
names omit the event payload's `On` prefix, such as `StartCallback` for `Event\Callbacks\OnStart`. This
wiring is private to the non-coroutine native factory rather than a reusable Server service. Each handler declares its
own constructor with `Psr\EventDispatcher\EventDispatcherInterface` and directly dispatches the matching
`Event\Callbacks\On*` payload. Listener failures propagate to the native callback caller. An `OnTask` listener can assign
`OnTask::$result`; the handler returns that value to Swoole. The native factory composes startup logging as an internal
`start` observer that runs after `OnStart` dispatch. Callback-handler resolution, conflicts, or registration failures
are logged and raised as `ServerCreationException`. Port callbacks such as `receive`, `request`, and WebSocket message
handling belong to their protocol components.

On real startup, Server writes one mode notice, one process-ID record, and one listener record per configuration through
`StdoutLoggerInterface`. Native modes use Swoole's `start` callback. Coroutine mode logs only after `start()` yields.

## Command-line startup

This package provides no console command. The `swoole:start` command belongs to
[`super-kernel/swoole`](https://github.com/super-kernel/swoole/blob/main/README.md). Server's listener mounts startup through `ServerInterface`
when it receives the main-process creation event and Server configuration enables the service.

## User processes

`Listener\MainProcessCreatedListener` listens to `SuperKernel\Swoole\Event\MainProcessCreated`.
It checks the declaration through injected `ConfigInterface`, then resolves `SwooleServerConfigInterface` through
its injected container during the event. This deferral keeps listener discovery, command help and unrelated commands
from loading optional runtime configuration. Absent or valid disabled configuration skips mounting.
Otherwise it mounts a child with `MainProcess::addProcess()`. In that child it resolves `ServerInterface` through DI
and invokes `start()`. The configuration has already passed validation; native construction and startup remain deferred
until the child runs.
The event supplies only MainProcess; activation uses Server's configuration, without reading Swoole's supervisor settings.
Server owns both this listener and its native or coroutine runtime. Server startup adds no Pool worker.
Server can run concurrently with a separate Pool child beneath MainProcess. Direct non-coroutine Server startup
forces foreground execution. Swoole's MainProcess owns optional daemonization and the main PID file; it sends
SIGTERM to mounted children in order on SIGTERM/SIGINT and waits for their exit. Server owns its descendant workers.
The main PID file records the final MainProcess PID, including after daemonization, rather than a Server PID.

Mark a class with `#[SuperKernel\Server\Attribute\UserProcess]` and implement `Contract\UserProcessInterface`.
Like Process's declaration, the Attribute accepts `?string $name = null`, `int $workNums = 1` and
`bool $enableCoroutine = false`. A null name uses the attributed class's fully qualified name, even if DI returns
another implementation. Counts must be in 0-1024, and explicit names must not be blank. For example,
`#[UserProcess('jobs', workNums: 2, enableCoroutine: true)]` declares two coroutine user workers.
The task's readable `enabled` hook controls registration. Its native option hooks are `redirectStdinStdout`,
`pipeType` and `enableCoroutine`; the Attribute's coroutine setting overrides the task's preference without mutating
the task. `execute(Swoole\Process $process, Swoole\Server $server): void`
receives the native user worker and its owning Server. Keep construction and property reads free of connections,
reactors and other business resources; initialize them inside `execute()` after the native Server forks.

`Provider\UserProcessManagerProvider` is the factory provider for `Contract\UserProcessManagerInterface`.
It collects every `UserProcess`-marked class in annotation discovery order and rejects classes that do not implement
`UserProcessInterface`, including disabled or zero-count declarations. It resolves each class with nonzero `workNums`
once through DI and calls `Manager\UserProcessManager::addBatch()` with the declaration's count, coroutine setting
and effective name. Zero-count declarations skip DI resolution. Disabled tasks add no workers and their native option
hooks are not read. Container binding correctness belongs to DI.

The manager follows Process's registration and query API:

- `add($process, $enableCoroutine = null, $name = null)` registers one task and returns the manager.
- `addBatch($workerNum, $process, $enableCoroutine = null, $name = null)` registers 0-1024 entries atomically.
  A single worker keeps its name; multiple workers use `name:0`, `name:1`, etc. Unnamed direct registrations use
  zero-based registration indexes. Names are case-sensitive, must not be blank, and may be full class names.
  The registry holds at most 1024 workers; a collision or invalid option leaves the batch unregistered.
- `all()` returns effective `UserProcessInterface` tasks in registration order, keyed by expanded name.
- `has($name)` checks exact registration. `get($name)` returns the same effective task as `all()` and throws
  `Exception\UserProcessNotFoundException` when the name is absent.

Register a `UserProcessInterface` or a `callable(Swoole\Process, Swoole\Server): void`. A null coroutine override
inherits the task's preference. Callables default to no redirection, a datagram pipe and coroutine execution disabled;
wrap one in `Definition\UserProcessDefinition` to supply different native options. Registration validates pipe types
and captures options in immutable effective tasks without changing the original implementation. The adapter delegates
`execute()` to the original callback; an already prepared definition keeps its identity when no override is needed.
Repeated `all()`, `has()` and `get()` calls do not resolve services, reread options or allocate native processes.

Complete registration before Server attaches its workers. The manager has no `start()` or `stop()` operation and owns
no Pool configuration or IPC settings; native Server owns user-worker execution and shutdown. Adding entries after
attachment changes only the local registry and does not mount them into a running Server.

When migrating existing declarations, rename `instances:` to `workNums:` and move `enabled:` to the implementation's
`public bool $enabled` property or getter. The third positional argument now selects `enableCoroutine`; it no longer
controls enablement. Set coroutine execution on the Attribute, including when the task's preference is true.
Replace Collector interface, implementation and provider references with `UserProcessManagerInterface`,
`Manager\UserProcessManager` and `Provider\UserProcessManagerProvider`. Read effective tasks directly from `all()`;
there is no DTO `task` field. Use `has()` before `get()` when a name may be absent.

`ServerProvider` calls the manager's `all()` after creation events and the daemonize=false reset, then creates each native
Process and calls `Server::addProcess()` in order before native startup. Multiple instances receive `name:0`,
`name:1`, and subsequent registration identities; names must be unique. These names are not OS process titles.
The Server start-attempt guard prevents duplicate attachment. Registration during creation events is included.

Native Server owns worker startup, reaping and restarting. Returning from `execute()` can cause a native restart.
No background READY protocol or restart policy is applied. Pure coroutine Servers skip reading and attaching workers;
factory preparation occurs when the manager is resolved, independently of Server mode.
Server delegates mode support and registration failures to the native API without additional checks or wrapping.
The constructor options and attachment boundary follow the
[Swoole 6.2.3 native implementation](https://github.com/swoole/swoole-src/blob/v6.2.3/ext-src/swoole_server.cc).

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
