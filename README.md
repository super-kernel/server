<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Server

`super-kernel/server` creates and starts the one Swoole server an application configures, publishes its creation and its main callbacks as events, and runs the user processes declared for it.

## Installation

```bash
composer require super-kernel/server
```

The package needs the Swoole extension `>=6.2.3 <6.3` and installs `super-kernel/swoole`, whose `swoole:start` command starts the server in a child process. The application must declare a `SwooleServerConfigInterface` configuration source, and SKERNEL builds the project. The server handles no protocol by itself: install a protocol package such as `super-kernel/http-server`, or attach your own callbacks.

## What it provides

- A `Swoole\Server`, `Swoole\Http\Server`, or `Swoole\WebSocket\Server` in base, process, or thread mode, or a `Swoole\Coroutine\Server`, built from the configuration.
- Several listeners on one server, created in the configured order.
- `ServerInterface`, which starts the server once in the foreground.
- `AfterMainServerCreated` and `AfterServerCreated`, where protocol packages and applications attach their callbacks before the server starts.
- An event for each of the 14 main Swoole callbacks, such as `OnStart`, `OnWorkerStart`, and `OnTask`, and startup log records.
- User processes declared with `#[UserProcess]` and attached to the server as its own workers.

It does not:

- handle HTTP, WebSocket, or raw connections; protocol packages and applications do;
- provide a console command; `super-kernel/swoole` provides `swoole:start`;
- run as a daemon, write a PID file, or handle shutdown signals; the main process of `super-kernel/swoole` does;
- restart or reap user processes itself; the Swoole server does.

## Quick start

In an application created from `super-kernel/skeleton`, which already declares `src/Config/SwooleServerConfig.php` with an HTTP listener on port 9600, this listener reports when the server starts. Save it as `src/Listener/AnnounceStart.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Listener;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Server\Event\Callbacks\OnStart;

#[Listener]
final readonly class AnnounceStart implements ListenerInterface
{
    public function listen(): array
    {
        return [
            OnStart::class,
        ];
    }

    public function process(object $event): void
    {
        assert($event instanceof OnStart);

        echo "Accepting connections on {$event->server->host}:{$event->server->port}", PHP_EOL;
    }
}
```

Build the project and start it:

```bash
skernel build
php bin/bootstrap.php swoole:start
```

It prints `Accepting connections on 127.0.0.1:9600` before the server's startup log lines. Press Ctrl+C to stop the server.

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Attribute\Configuration` | Attribute | Declares the application's `SwooleServerConfigInterface` source. |
| `SuperKernel\Server\Config\SwooleServerConfigInterface` | Interface | Whether the server runs, its mode, its listeners, and its Swoole settings. |
| `SuperKernel\Server\Constants\Mode` | Enum | `Base`, `Process`, `Thread`, or `Coroutine`. |
| `SuperKernel\Server\Constants\Protocol` | Enum | `Raw`, `Http`, or `WebSocket` for a listener. |
| `SuperKernel\Server\Contract\ServerInterface` | Interface | `getServer()` returns the native server; `start()` starts it once. |
| `SuperKernel\Server\Event\AfterMainServerCreated` | Event | Published once before startup; `server()` returns the native server. |
| `SuperKernel\Server\Event\AfterServerCreated` | Event | Published for each listener, main first; `server()` and `configuration()` return the listener and its array. |
| `SuperKernel\Server\Event\Callbacks\OnStart` | Event | Swoole's `start` callback. |
| `SuperKernel\Server\Event\Callbacks\OnBeforeShutdown` | Event | Swoole's `beforeShutdown` callback. |
| `SuperKernel\Server\Event\Callbacks\OnShutdown` | Event | Swoole's `shutdown` callback. |
| `SuperKernel\Server\Event\Callbacks\OnManagerStart` | Event | Swoole's `managerStart` callback. |
| `SuperKernel\Server\Event\Callbacks\OnManagerStop` | Event | Swoole's `managerStop` callback. |
| `SuperKernel\Server\Event\Callbacks\OnWorkerStart` | Event | Swoole's `workerStart` callback, with `workerId`. |
| `SuperKernel\Server\Event\Callbacks\OnWorkerStop` | Event | Swoole's `workerStop` callback, with `workerId`. |
| `SuperKernel\Server\Event\Callbacks\OnWorkerExit` | Event | Swoole's `workerExit` callback, with `workerId`. |
| `SuperKernel\Server\Event\Callbacks\OnWorkerError` | Event | Swoole's `workerError` callback, with the exit status. |
| `SuperKernel\Server\Event\Callbacks\OnTask` | Event | Swoole's `task` callback; a listener sets `result` to answer the task. |
| `SuperKernel\Server\Event\Callbacks\OnFinish` | Event | Swoole's `finish` callback, with the finished task. |
| `SuperKernel\Server\Event\Callbacks\OnPipeMessage` | Event | Swoole's `pipeMessage` callback, with the message. |
| `SuperKernel\Server\Event\Callbacks\OnBeforeReload` | Event | Swoole's `beforeReload` callback. |
| `SuperKernel\Server\Event\Callbacks\OnAfterReload` | Event | Swoole's `afterReload` callback. |
| `SuperKernel\Server\Attribute\UserProcess` | Attribute | Declares a user process with an optional name, a worker count, and coroutine support. |
| `SuperKernel\Server\Contract\UserProcessInterface` | Interface | Implemented by a user process: its options and `execute()`. |
| `SuperKernel\Server\Contract\UserProcessManagerInterface` | Interface | Registers and looks up user processes before the server starts. |
| `SuperKernel\Server\Definition\UserProcessDefinition` | Class | Wraps a callback with its own user-process options. |

## Behavior to know

- `swoole:start` reads and validates the configuration even when `enable()` returns `false`; without a declared source, or with `enable()` returning `false`, it starts no server. An invalid configuration fails with a `SuperKernel\Di\Exception\ResolutionException` whose previous exception is a `SuperKernel\Server\Exception\InvalidServerConfigurationException`, for example `Listener name "http" at position 2 duplicates the listener at position 1.`
- `start()` publishes `AfterMainServerCreated` and then `AfterServerCreated` for every listener before the server starts; an exception from a listener prevents the start. A second call raises `SuperKernel\Server\Exception\ServerStartException` with `The configured Swoole service may only be started once.`
- A server that cannot be created, for example because its port is in use, makes `start()` raise `Unable to resolve the configured Swoole service from container entry Swoole\Server.`, with the cause as the previous exception; under `swoole:start` that ends the run.
- The server always runs in the foreground: `daemonize` is set to `false` just before it starts, whatever the settings say.
- An exception from a callback listener propagates to Swoole's callback.
- Listeners and user processes are found in the build metadata: run `skernel build` again after adding, removing, or changing them or the configuration class, or start with `skernel run`, which builds first.

## Documentation

The developer documentation covers the configuration rules, protocol handlers, callback events, user processes, starting the server yourself, the error messages, and what not to do: [SuperKernel Server](https://github.com/super-kernel/.github/blob/main/docs/components/server.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
