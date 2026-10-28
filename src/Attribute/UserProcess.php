<?php

declare(strict_types=1);

namespace SuperKernel\Server\Attribute;

use Attribute;
use InvalidArgumentException;

use function trim;

/** Declares Server-owned workers; their restart policy belongs to the native Server. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class UserProcess
{
    /**
     * @param string|null $name null uses the attributed class's fully qualified name
     *
     * @throws InvalidArgumentException if an explicit name is empty or the declared process count is outside 0-1024
     */
    public function __construct(
        public ?string $name = null,
        public int     $workNums = 1,
        public bool    $enableCoroutine = false,
    )
    {
        if (null !== $name && '' === trim($name)) {
            throw new InvalidArgumentException('UserProcess name must not be empty.');
        }
        if (0 > $workNums || 1024 < $workNums) {
            throw new InvalidArgumentException('UserProcess count must be between 0 and 1024.');
        }
    }
}
