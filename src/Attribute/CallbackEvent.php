<?php
declare(strict_types=1);

namespace SuperKernel\Server\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CallbackEvent
{
	public function __construct(private string $event)
	{
	}

	public function getEventName(): string
	{
		return $this->event;
	}
}