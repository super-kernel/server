<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use SuperKernel\Server\Config\SwooleServerConfigInterface;
use SuperKernel\Server\Constants\Mode;

final readonly class SwooleServerConfig implements SwooleServerConfigInterface
{
    /** @var array<array-key, mixed> */
    private array $listeners;

    /**
     * @param array<array-key, mixed>|null $listeners
     * @param array<array-key, mixed>      $settings
     */
    public function __construct(
        private Mode  $mode,
        ?array        $listeners = null,
        private array $settings = [],
        private bool  $enable = true,
    )
    {
        $this->listeners = $listeners ?? [
            [
                'name' => 'main',
                'host' => '127.0.0.1',
                'port' => 0,
            ],
        ];
    }

    public function enable(): bool
    {
        return $this->enable;
    }

    public function mode(): Mode
    {
        return $this->mode;
    }

    public function listeners(): array
    {
        return $this->listeners;
    }

    public function settings(): array
    {
        return $this->settings;
    }
}
