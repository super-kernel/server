<?php

declare(strict_types=1);

namespace SuperKernel\Server\Manager;

use SuperKernel\Server\Contract\UserProcessInterface;
use SuperKernel\Server\Contract\UserProcessManagerInterface;
use SuperKernel\Server\Exception\UserProcessNotFoundException;

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
        $entries = UserProcessRegistration::entries($this->processes, 1, $process, $enableCoroutine, $name);

        foreach ($entries as $key => $task) {
            $this->processes[$key] = $task;
        }

        return $this;
    }

    public function addBatch(
        int                           $workerNum,
        UserProcessInterface|callable $process,
        ?bool                         $enableCoroutine = null,
        ?string                       $name = null,
    ): self
    {
        $entries = UserProcessRegistration::entries($this->processes, $workerNum, $process, $enableCoroutine, $name);

        foreach ($entries as $key => $task) {
            $this->processes[$key] = $task;
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
