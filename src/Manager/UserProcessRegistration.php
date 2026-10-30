<?php

declare(strict_types=1);

namespace SuperKernel\Server\Manager;

use InvalidArgumentException;
use LogicException;
use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Definition\UserProcessDefinition;
use Swoole\Process;
use Swoole\Server;

use function count;
use function in_array;
use function trim;

/**
 * Validates one user-process registration batch and expands it into named effective tasks.
 *
 * @internal
 */
final class UserProcessRegistration
{
    /**
     * Returns no entries for a zero count or a disabled task; a disabled task's native option hooks are not read.
     *
     * @param array<array-key, UserProcessInterface>               $processes       registered tasks keyed by name
     * @param UserProcessInterface|callable(Process, Server): void $process
     * @param bool|null                                            $enableCoroutine null inherits the task's preference
     *
     * @return array<array-key, UserProcessInterface> new effective tasks keyed by expanded name in registration order
     *
     * @throws InvalidArgumentException if workerNum is outside 0-1024 or the task's pipe type is invalid
     * @throws LogicException           if a name is blank/duplicated or registration exceeds 1024 workers
     */
    public static function entries(
        array                         $processes,
        int                           $workerNum,
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine,
        ?string                       $name,
    ): array
    {
        if (0 > $workerNum || 1024 < $workerNum) {
            throw new InvalidArgumentException('User process count must be between 0 and 1024.');
        }
        if (0 === $workerNum || ($process instanceof UserProcessInterface && !$process->enabled)) {
            return [];
        }
        $offset = count($processes);
        if (1024 < $offset + $workerNum) {
            throw new LogicException('At most 1024 user workers may be registered.');
        }
        if (null !== $name && '' === trim($name)) {
            throw new LogicException('User process names must not be blank.');
        }
        $names = [];
        for ($index = 0; $index < $workerNum; ++$index) {
            $workerName = null === $name ? (string) ($offset + $index)
                : $name . (1 === $workerNum ? '' : ':' . $index);
            if (isset($processes[$workerName])) {
                throw new LogicException('Duplicate user process name: ' . $workerName . '.');
            }
            $names[] = $workerName;
        }
        if ($process instanceof UserProcessInterface) {
            $redirect = $process->redirectStdinStdout;
            $pipeType = $process->pipeType;
            $enableCoroutine ??= $process->enableCoroutine;
            if (!in_array($pipeType, [
                Process::PIPE_TYPE_NONE,
                Process::PIPE_TYPE_STREAM,
                Process::PIPE_TYPE_DGRAM,
            ], true)) {
                throw new InvalidArgumentException('Invalid user process pipe type: ' . $pipeType);
            }
            if (!$process instanceof UserProcessDefinition || $enableCoroutine !== $process->enableCoroutine) {
                $process = new UserProcessDefinition($process->execute(...), $redirect, $pipeType, $enableCoroutine);
            }
        } else {
            $process = new UserProcessDefinition($process(...), enableCoroutine: $enableCoroutine ?? false);
        }
        $entries = [];
        foreach ($names as $workerName) {
            $entries[$workerName] = $process;
        }
        return $entries;
    }
}
