<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use Psr\Log\LoggerTrait;
use Stringable;
use SuperKernel\Contract\StdoutLoggerInterface;

final class RecordingStdoutLogger implements StdoutLoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
