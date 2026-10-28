<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SuperKernel\Server\Tests\Fixture\UserProcessScenario;
use Swoole\Process;
use Throwable;

use function array_column;
use function array_map;
use function array_unique;
use function bin2hex;
use function dirname;
use function extension_loaded;
use function file;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function json_decode;
use function microtime;
use function mkdir;
use function posix_kill;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function trim;
use function unlink;
use function usleep;
use function var_export;

use const FILE_IGNORE_NEW_LINES;
use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;
use const PHP_OS_FAMILY;
use const SIGTERM;

final class UserProcessTest extends TestCase
{
    /** @return iterable<string, array{string, int, bool, bool, bool, 5?: bool}> */
    public static function nativeOptions(): iterable
    {
        yield 'base datagram' => [
            'base',
            Process::PIPE_TYPE_DGRAM,
            false,
            false,
            false,
        ];
        yield 'process datagram' => [
            'process',
            Process::PIPE_TYPE_DGRAM,
            false,
            false,
            false,
        ];
        yield 'no pipe' => [
            'base',
            Process::PIPE_TYPE_NONE,
            false,
            false,
            false,
        ];
        yield 'stream' => [
            'base',
            Process::PIPE_TYPE_STREAM,
            false,
            false,
            false,
        ];
        yield 'redirect forces stream' => [
            'base',
            Process::PIPE_TYPE_DGRAM,
            true,
            false,
            false,
        ];
        yield 'coroutine' => [
            'process',
            Process::PIPE_TYPE_DGRAM,
            false,
            true,
            false,
        ];
        yield 'base restarts worker' => [
            'base',
            Process::PIPE_TYPE_DGRAM,
            false,
            false,
            true,
        ];
        yield 'process restarts coroutine worker' => [
            'process',
            Process::PIPE_TYPE_NONE,
            false,
            true,
            true,
        ];
        yield 'base callable' => [
            'base',
            Process::PIPE_TYPE_DGRAM,
            false,
            false,
            false,
            true,
        ];
        yield 'process coroutine callable' => [
            'process',
            Process::PIPE_TYPE_DGRAM,
            false,
            true,
            false,
            true,
        ];
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    #[DataProvider('nativeOptions')]
    public function testServerOwnsRegisteredWorkerStartupAndRestart(
        string $mode,
        int    $pipeType,
        bool   $redirect,
        bool   $coroutine,
        bool   $restart,
        bool   $callable = false,
    ): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || !extension_loaded('swoole') || !extension_loaded('sockets')) {
            self::markTestSkipped('Native user worker scenarios require Linux, Swoole and sockets.');
        }
        $directory = sys_get_temp_dir() . '/sk-user-process-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $component = dirname(__DIR__, 2);
        $autoload = $component . '/vendor/autoload.php';
        file_put_contents($directory . '/run.php', '<?php require ' . var_export($autoload, true) . ';'
            . '$loader = new Composer\\Autoload\\ClassLoader();'
            . '$loader->addPsr4("SuperKernel\\\\Server\\\\Tests\\\\",' . var_export($component . '/tests', true) . ');'
            . '$loader->register(true);'
            . '\\' . UserProcessScenario::class . '::run(' . var_export($directory, true) . ','
            . var_export($mode, true) . ',' . var_export($pipeType, true) . ',' . var_export($redirect, true) . ','
            . var_export(
                $coroutine,
                true,
            ) . ',' . var_export($restart, true) . ',' . var_export($callable, true) . ');');
        $output = $directory . '/output.txt';
        $process = proc_open([
            PHP_BINARY,
            $directory . '/run.php',
        ], [
            0 => [
                'file',
                '/dev/null',
                'r',
            ],
            1 => [
                'file',
                $output,
                'w',
            ],
            2 => [
                'file',
                $output,
                'a',
            ],
        ], $pipes);
        self::assertIsResource($process);
        try {
            $deadline = microtime(true) + 6;
            do {
                usleep(10000);
                $status = proc_get_status($process);
            } while ($status['running'] && microtime(true) < $deadline);
            self::assertFalse($status['running'], 'Fixture Server did not stop: ' . file_get_contents($output));
            self::assertSame(0, $status['exitcode'], (string) file_get_contents($output));
            self::assertSame('', trim((string) file_get_contents($output)));
            self::assertFileExists($directory . '/result.json');
            $result = json_decode(file_get_contents($directory . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(-1, $result['remainingChild']);
            self::assertFileExists($directory . '/workers.jsonl');
            $workers = array_map(
                static fn(string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
                file($directory . '/workers.jsonl', FILE_IGNORE_NEW_LINES),
            );
            self::assertCount(2, $workers);
            self::assertCount(2, array_unique(array_column($workers, 'pid')));
            self::assertCount($restart ? 1 : 2, array_unique(array_column($workers, 'id')));
            foreach ($workers as $worker) {
                self::assertSame($worker['pid'], $worker['nativePid']);
                self::assertTrue($worker['sameServer']);
                self::assertNotSame($result['pid'], $worker['pid']);
                self::assertSame($redirect ? Process::PIPE_TYPE_STREAM : $pipeType, $worker['pipeType']);
                self::assertSame($coroutine, $worker['coroutine']);
                self::assertFalse(posix_kill($worker['pid'], 0), 'The Server must reap its user workers.');
            }
        } finally {
            // Only terminate the fixture launched by this test; the Server owns and stops its workers.
            if (proc_get_status($process)['running']) {
                proc_terminate($process, SIGTERM);
            }
            proc_close($process);
            foreach (glob($directory . '/*') as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
