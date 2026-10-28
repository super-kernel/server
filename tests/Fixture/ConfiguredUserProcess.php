<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use SuperKernel\Server\Attribute\UserProcess;
use SuperKernel\Server\Contract\UserProcessInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Server;

use function file_exists;
use function file_put_contents;
use function fopen;
use function getmypid;
use function json_encode;
use function socket_get_option;
use function socket_import_stream;
use function usleep;

use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;
use const SO_TYPE;
use const SOL_SOCKET;

#[UserProcess]
final class ConfiguredUserProcess implements UserProcessInterface
{
    public bool $enabled = true;
    public ?Server $expectedServer = null;

    public function __construct(
        private readonly string $logFile,
        public int              $pipeType = Process::PIPE_TYPE_DGRAM,
        public bool             $redirectStdinStdout = false,
        public bool             $enableCoroutine = false,
        private readonly bool   $exitOnce = false,
    )
    {
    }

    public function execute(Process $process, Server $server): void
    {
        $first = !file_exists($this->logFile);
        $type = Process::PIPE_TYPE_NONE;
        if (0 < $process->pipe) {
            $stream = fopen('php://fd/' . $process->pipe, 'r+');
            $type = socket_get_option(socket_import_stream($stream), SOL_SOCKET, SO_TYPE);
        }
        file_put_contents($this->logFile, json_encode([
            'pid'        => getmypid(),
            'nativePid'  => $process->pid,
            'sameServer' => $server === $this->expectedServer,
            'id'         => $process->id,
            'pipeType'   => $type,
            'coroutine'  => 0 <= Coroutine::getCid(),
        ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX);
        if ($first && $this->exitOnce) {
            return;
        }
        while (true) {
            if ($this->enableCoroutine) {
                Coroutine::sleep(0.01);
            } else {
                usleep(10000);
            }
        }
    }
}
