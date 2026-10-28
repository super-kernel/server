<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function __construct(private readonly ?Closure $onDispatch = null)
    {
    }

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        $this->onDispatch?->__invoke($event);

        return $event;
    }
}
