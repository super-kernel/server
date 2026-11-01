<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Server

`super-kernel/server` 构造并启动一个由应用配置的 Swoole 服务。它提供原生和协程服务器的构造、面向应用的 `ServerInterface`、启动生命周期事件、Swoole 主服务器回调事件、启动日志以及用户进程。

它不提供 TCP、HTTP、WebSocket 或其他任何协议处理器：协议组件在 Server 针对每个监听器的启动事件中挂接自己的回调。关闭、重启、重载、守护进程、PID 文件和信号的编排也都不属于本组件。

## 应用配置

应用用 `#[Configuration]` 标记 `SuperKernel\Server\Config\SwooleServerConfigInterface` 的一个实现。容器会校验它，并以 `SwooleServerConfigInterface` 提供同一个对象。第一个监听器是普通的主监听器。按 `WebSocket > Http > Raw` 的顺序，它的协议能力必须不低于其他每个监听器；其他原生监听器按声明顺序创建。

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

`listeners()` 返回一个非空列表，其中的数组形状由该接口的 PHPDoc 描述。每个监听器都需要非空字符串的 `name` 和 `host` 字段，以及整数的 `port`。可选字段有整数 `socketType`、布尔值 `ssl` 和 `reusePort`，以及 `Protocol` 类型的 `protocol`。省略的选项在使用时分别默认为 `SWOOLE_SOCK_TCP`、`false`、`false` 和 `Protocol::Raw`；校验不会把默认字段写回源数组。未知的键以及显式传入的无效值（包括 `null`）都会被拒绝。服务器设置使用字符串键；原生 Swoole 选项的校验和启动失败由 Swoole 自身报告。

原生装配和生命周期事件原样使用经过校验的监听器数组，保留其字段和顺序。运行期间配置 getter 的结果必须保持稳定：校验不会复制或冻结应用对象。用 `#[Configuration]` 定制取值；更高优先级的 DI `Provider` 覆盖会绕过校验。

每个配置源都要实现 `enable(): bool`。没有声明时，会跳过自动挂载，也不会解析该接口。已声明的配置会在检查 enable 之前完成校验，禁用的配置也一样，加载和校验失败都会向外传播。返回 `false` 会禁用自动挂载，但不影响显式调用 `ServerInterface::start()`。

监听器名称是非空、区分大小写、唯一的应用标识。原生模式通过 socket 类型表示 TLS。协程模式只支持恰好一个原始（raw）监听器，并使用该监听器的 `ssl` 和 `reusePort` 字段。这个唯一的协程监听器被视为逻辑上的主服务；Server 不会为它虚构默认的 `handle()` 回调。

## 原生服务器

对于 base、process 以及受支持的 thread 模式，容器提供 `Swoole\Server`，根据主协议在 `Swoole\Server`、`Swoole\Http\Server` 和 `Swoole\WebSocket\Server` 之间选择。它应用设置、添加额外的端口，并注册 Server 的主回调事件和启动日志器。协程模式改为提供 `Swoole\Coroutine\Server`，并拒绝普通模式。

只有当容器通过 Server 自己的提供者构建 `Swoole\Server` 时，才会注册主回调和启动日志。应用自己为 `Swoole\Server` 提供的 DI 提供者优先，并有意绕过这一行为。

## ServerInterface 与启动

`SuperKernel\Server\Contract\ServerInterface` 解析由配置选定的、被保留的那个确切的 `Swoole\Server` 或 `Swoole\Coroutine\Server`。`getServer()` 为底层集成暴露该原生对象，而 `start()` 是规范的应用边界：

```php
use SuperKernel\Server\Contract\ServerInterface;

$container->get(ServerInterface::class)->start();
```

一个 `ServerInterface` 实例只接受一次启动尝试。进入原生运行时之前，它先同步分发一次 `Event\AfterMainServerCreated`，然后按顺序为每个配置的监听器（包括主监听器）各分发一次 `Event\AfterServerCreated`。后者通过 `configuration(): array` 暴露确切的原生服务器或端口，以及原始的监听器数组，其形状为 `SwooleServerConfigInterface` 上记录的 `ListenerConfiguration`。通过 `configuration()['name']` 读取监听器名称，对可选的协议使用 `['protocol'] ?? Protocol::Raw`。监听器失败会阻止原生启动。

所有创建事件结束后，非协程服务器会在调用原生 `start()` 之前把 `daemonize` 重置为 false。这会覆盖配置和创建事件监听器所做的修改；该设置被拒绝时，启动会失败。

普通的启动返回 false 时会变为 `ServerStartException`。协程启动只在需要时进入 `Swoole\Coroutine\run()`，保留内部的失败，对调用方保持阻塞，并且只在原生协程服务器让出之后才记录启动日志。缺少协程协议处理器时，Server 不会掩盖这个问题。

## 主回调与启动日志

Server 注册所有原生主服务器回调，并通过 `Psr\EventDispatcher\EventDispatcherInterface` 分发对应的 `Event\Callbacks\On*` 事件：`OnStart`、`OnShutdown`、`OnBeforeShutdown`、`OnManagerStart`、`OnManagerStop`、`OnWorkerStart`、`OnWorkerStop`、`OnWorkerExit`、`OnWorkerError`、`OnTask`、`OnFinish`、`OnPipeMessage`、`OnBeforeReload` 和 `OnAfterReload`。监听器的失败会传播到原生回调的调用方。`OnTask` 监听器可以设置 `OnTask::$result`，该值会返回给 Swoole。回调注册失败会被记录日志，并以 `ServerCreationException` 抛出。`receive`、`request` 和 WebSocket 消息处理等端口回调属于协议组件。

真正启动时，Server 通过 `StdoutLoggerInterface` 写出一条模式说明、一条进程 ID 记录，以及每个配置的监听器各一条记录。原生模式在 Swoole 的 `start` 回调中、`OnStart` 分发之后记录日志。协程模式只在 `start()` 让出之后记录日志。

## 在主进程下运行

本包不提供控制台命令；`swoole:start` 属于 [`super-kernel/swoole`](https://github.com/super-kernel/swoole/blob/main/README.md)。在 `SuperKernel\Swoole\Event\MainProcessCreated` 事件中，Server 检查自己的配置。配置缺失或被禁用时不挂载任何东西，命令帮助和无关的命令也从不加载它。配置启用时，通过 `MainProcess::addProcess()` 挂载一个子进程，在其中解析 `ServerInterface` 并调用 `start()`；原生构造会推迟到该子进程运行时才进行。

Server 可以与主进程下另一个独立的 Pool 子进程并行运行。直接启动非协程服务器时会强制在前台运行。Swoole 的主进程负责可选的守护进程化和主 PID 文件，该文件记录主进程最终的 PID，而不是 Server 的 PID。收到 SIGTERM 或 SIGINT 时，主进程按顺序向已挂载的子进程发送 SIGTERM，并等待它们退出。Server 负责它自己的后代工作进程。

## 用户进程

用 `#[SuperKernel\Server\Attribute\UserProcess]` 标记一个类，并实现 `Contract\UserProcessInterface`。该注解接受 `?string $name = null`、`int $workNums = 1` 和 `bool $enableCoroutine = false`。名称为 null 时使用所标注类的完全限定名，即使 DI 返回的是另一个实现。数量必须在 0-1024 之间，显式名称不能为空白。例如，`#[UserProcess('jobs', workNums: 2, enableCoroutine: true)]` 声明了两个协程用户工作进程。

任务可读的 `enabled` hook 控制是否注册。它的原生选项 hook 是 `redirectStdinStdout`、`pipeType` 和 `enableCoroutine`；注解的协程设置会覆盖任务的偏好，但不修改任务本身。`execute(Swoole\Process $process, Swoole\Server $server): void` 接收原生用户工作进程及其所属的 Server。请让构造和属性读取都不涉及连接、reactor 和其他业务资源；在原生 Server fork 之后，再在 `execute()` 中初始化它们。

容器提供的 `Contract\UserProcessManagerInterface` 已按发现顺序注册了所有 `#[UserProcess]` 类。没有实现 `UserProcessInterface` 的类会被拒绝，禁用的或数量为零的声明也不例外。每个 `workNums` 非零的类会被解析一次；数量为零的声明不会被解析，禁用的任务不会添加工作进程，其原生选项 hook 也不会被读取。

管理器沿用 Process 的注册和查询 API：

- `add($process, $enableCoroutine = null, $name = null)` 注册一个任务并返回管理器。
- `addBatch($workerNum, $process, $enableCoroutine = null, $name = null)` 以原子方式注册 0-1024 个条目。只有一个工作进程时保留其名称；多个工作进程使用 `name:0`、`name:1` 等。未命名的直接注册使用从零开始的注册序号。名称区分大小写，不能为空白，可以是完整的类名。注册表最多容纳 1024 个工作进程；名称冲突或选项无效时，整个批次都不会被注册。
- `all()` 按注册顺序返回生效后的 `UserProcessInterface` 任务，以展开后的名称为键。
- `has($name)` 检查确切的注册。`get($name)` 返回与 `all()` 中相同的生效任务，名称不存在时抛出 `Exception\UserProcessNotFoundException`。

可以注册 `UserProcessInterface` 或 `callable(Swoole\Process, Swoole\Server): void`。协程覆盖为 null 时继承任务的偏好。可调用值默认不重定向、使用数据报管道并禁用协程执行；需要不同的原生选项时，用 `Definition\UserProcessDefinition` 包装它。注册时会校验管道类型，并把选项固定在不可变的生效任务中，而不改变原始实现。重复调用 `all()`、`has()` 和 `get()` 不会解析服务、重新读取选项或分配原生进程。

请在 Server 挂接其工作进程之前完成注册。在创建事件和 `daemonize=false` 重置之后、原生启动之前，Server 按顺序为每个已注册的任务创建一个原生 `Swoole\Process`，并通过 `Server::addProcess()` 挂接；在创建事件期间完成的注册也包括在内。名称是注册标识，不是操作系统的进程标题。管理器没有 `start()` 或 `stop()` 操作，也不负责 Pool 配置或 IPC 设置。挂接之后再添加的条目只会改变本地注册表，不会挂载到正在运行的 Server 中。

原生 Server 负责工作进程的启动、回收和重启。从 `execute()` 返回可能导致原生重启。不采用后台 READY 协议或重启策略。纯协程 Server 不挂接用户工作进程。模式支持和注册失败直接来自原生 API，不做额外检查或包装；参见 [Swoole 6.2.3 原生实现](https://github.com/swoole/swoole-src/blob/v6.2.3/ext-src/swoole_server.cc)。
