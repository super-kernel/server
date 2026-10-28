<?php

declare(strict_types=1);

namespace SuperKernel\Server\Exception;

use RuntimeException;

final class InvalidServerConfigurationException extends RuntimeException implements SwooleServerExceptionInterface
{
}
