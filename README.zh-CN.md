<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Server

`super-kernel/server` 创建并启动应用配置的那一个 Swoole 服务器，把服务器的创建和主回调作为事件发布，并运行为它声明的用户进程。

## 安装

```bash
composer require super-kernel/server
```

本包需要 `>=6.2.3 <6.3` 的 Swoole 扩展，并会安装 `super-kernel/swoole`，其 `swoole:start` 命令在子进程中启动服务器。应用必须声明一个 `SwooleServerConfigInterface` 配置源，并由 SKERNEL 构建项目。服务器本身不处理任何协议：请安装 `super-kernel/http-server` 等协议包，或者挂上自己的回调。

## 提供的能力

- 根据配置构建的 base、process 或 thread 模式的 `Swoole\Server`、`Swoole\Http\Server` 或 `Swoole\WebSocket\Server`，或者 `Swoole\Coroutine\Server`。
- 一个服务器上的多个监听器，按配置的顺序创建。
- `ServerInterface`：在前台启动服务器一次。
- `AfterMainServerCreated` 和 `AfterServerCreated`：协议包和应用在服务器启动之前在这里挂上它们的回调。
- 14 个 Swoole 主回调各自对应的事件（例如 `OnStart`、`OnWorkerStart` 和 `OnTask`），以及启动日志记录。
- 用 `#[UserProcess]` 声明、作为服务器自己的工作进程挂到服务器上的用户进程。

它不会：

- 处理 HTTP、WebSocket 或原始连接；这由协议包和应用负责；
- 提供控制台命令；`swoole:start` 由 `super-kernel/swoole` 提供；
- 以守护进程运行、写 PID 文件或处理关闭信号；这由 `super-kernel/swoole` 的主进程负责；
- 自己重启或回收用户进程；这由 Swoole 服务器负责。

## 快速开始

在由 `super-kernel/skeleton` 创建的应用中（它已经声明了 `src/Config/SwooleServerConfig.php`，其中有一个端口为 9600 的 HTTP 监听器），下面的监听器会在服务器启动时报告。将它保存为 `src/Listener/AnnounceStart.php`：

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

构建项目并启动它：

```bash
skernel build
php bin/bootstrap.php swoole:start
```

它会在服务器的启动日志行之前打印 `Accepting connections on 127.0.0.1:9600`。按 Ctrl+C 停止服务器。

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Attribute\Configuration` | 注解 | 声明应用的 `SwooleServerConfigInterface` 配置源。 |
| `SuperKernel\Server\Config\SwooleServerConfigInterface` | 接口 | 服务器是否运行，以及它的模式、监听器和 Swoole 设置。 |
| `SuperKernel\Server\Constants\Mode` | 枚举 | `Base`、`Process`、`Thread` 或 `Coroutine`。 |
| `SuperKernel\Server\Constants\Protocol` | 枚举 | 监听器的 `Raw`、`Http` 或 `WebSocket`。 |
| `SuperKernel\Server\Contract\ServerInterface` | 接口 | `getServer()` 返回原生服务器；`start()` 启动它一次。 |
| `SuperKernel\Server\Event\AfterMainServerCreated` | 事件 | 在启动之前发布一次；`server()` 返回原生服务器。 |
| `SuperKernel\Server\Event\AfterServerCreated` | 事件 | 为每个监听器发布一次，主监听器最先；`server()` 和 `configuration()` 返回该监听器及其数组。 |
| `SuperKernel\Server\Event\Callbacks\OnStart` | 事件 | Swoole 的 `start` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnBeforeShutdown` | 事件 | Swoole 的 `beforeShutdown` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnShutdown` | 事件 | Swoole 的 `shutdown` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnManagerStart` | 事件 | Swoole 的 `managerStart` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnManagerStop` | 事件 | Swoole 的 `managerStop` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnWorkerStart` | 事件 | Swoole 的 `workerStart` 回调，带有 `workerId`。 |
| `SuperKernel\Server\Event\Callbacks\OnWorkerStop` | 事件 | Swoole 的 `workerStop` 回调，带有 `workerId`。 |
| `SuperKernel\Server\Event\Callbacks\OnWorkerExit` | 事件 | Swoole 的 `workerExit` 回调，带有 `workerId`。 |
| `SuperKernel\Server\Event\Callbacks\OnWorkerError` | 事件 | Swoole 的 `workerError` 回调，带有退出状态。 |
| `SuperKernel\Server\Event\Callbacks\OnTask` | 事件 | Swoole 的 `task` 回调；监听器设置 `result` 来答复任务。 |
| `SuperKernel\Server\Event\Callbacks\OnFinish` | 事件 | Swoole 的 `finish` 回调，带有已完成的任务。 |
| `SuperKernel\Server\Event\Callbacks\OnPipeMessage` | 事件 | Swoole 的 `pipeMessage` 回调，带有消息。 |
| `SuperKernel\Server\Event\Callbacks\OnBeforeReload` | 事件 | Swoole 的 `beforeReload` 回调。 |
| `SuperKernel\Server\Event\Callbacks\OnAfterReload` | 事件 | Swoole 的 `afterReload` 回调。 |
| `SuperKernel\Server\Attribute\UserProcess` | 注解 | 声明一个用户进程，可带名称、工作进程数量和协程支持。 |
| `SuperKernel\Server\Contract\UserProcessInterface` | 接口 | 由用户进程实现：它的选项和 `execute()`。 |
| `SuperKernel\Server\Contract\UserProcessManagerInterface` | 接口 | 在服务器启动之前注册和查找用户进程。 |
| `SuperKernel\Server\Definition\UserProcessDefinition` | 类 | 用自己的用户进程选项包装一个回调。 |

## 需要了解的行为

- 即使 `enable()` 返回 `false`，`swoole:start` 也会读取并校验配置；没有声明配置源，或者 `enable()` 返回 `false` 时，它不会启动服务器。无效的配置会以 `SuperKernel\Di\Exception\ResolutionException` 失败，其前一个异常为 `SuperKernel\Server\Exception\InvalidServerConfigurationException`，例如 `Listener name "http" at position 2 duplicates the listener at position 1.`
- `start()` 在服务器启动之前先发布 `AfterMainServerCreated`，再为每个监听器发布 `AfterServerCreated`；监听器抛出的异常会阻止启动。第二次调用会抛出 `SuperKernel\Server\Exception\ServerStartException`，消息为 `The configured Swoole service may only be started once.`
- 无法创建服务器时（例如端口已被占用），`start()` 会抛出 `Unable to resolve the configured Swoole service from container entry Swoole\Server.`，原因作为前一个异常（previous）；在 `swoole:start` 下，这会结束本次运行。
- 服务器始终在前台运行：无论设置如何，`daemonize` 都会在启动之前被设为 `false`。
- 回调监听器抛出的异常会传播到 Swoole 的回调。
- 监听器和用户进程从构建元数据中发现：添加、删除或修改它们或配置类之后，请重新运行 `skernel build`，或者用会先构建的 `skernel run` 启动。

## 文档

开发者文档介绍了配置规则、协议处理器、回调事件、用户进程、自行启动服务器、错误消息以及不要这样做的事项：[SuperKernel Server](https://github.com/super-kernel/.github/blob/main/docs/components/server.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
