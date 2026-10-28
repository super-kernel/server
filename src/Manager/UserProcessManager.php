<?php

declare(strict_types=1);

namespace SuperKernel\Server\Manager;

use InvalidArgumentException;
use LogicException;
use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Definition\UserProcessDefinition;
use SuperKernel\Server\Exception\UserProcessNotFoundException;
use Swoole\Process;

use function count;
use function in_array;
use function trim;

final class UserProcessManager implements UserProcessManagerInterface
{
    /** @var array<array-key, UserProcessInterface> */
    private array $processes = [];

    public function add(
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine = null,
        ?string                       $name = null,
    ): self
    {
        return $this->addBatch(1, $process, $enableCoroutine, $name);
    }

    public function addBatch(
        int                           $workerNum,
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine = null,
        ?string                       $name = null,
    ): self
    {
        if (0 > $workerNum || 1024 < $workerNum) {
            throw new InvalidArgumentException('User process count must be between 0 and 1024.');
        }
        if (0 === $workerNum || ($process instanceof UserProcessInterface && !$process->enabled)) {
            return $this;
        }
        $offset = count($this->processes);
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
            if ($this->has($workerName)) {
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
        foreach ($names as $workerName) {
            $this->processes[$workerName] = $process;
        }
        return $this;
    }

    public function all(): array
    {
        return $this->processes;
    }

    public function has(string $name): bool
    {
        return isset($this->processes[$name]);
    }

    public function get(string $name): UserProcessInterface
    {
        return $this->processes[$name]
            ?? throw new UserProcessNotFoundException('User process not found: "' . $name . '".');
    }
}
