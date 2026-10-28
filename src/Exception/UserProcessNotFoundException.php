<?php

declare(strict_types=1);

namespace SuperKernel\Server\Exception;

use OutOfBoundsException;

final class UserProcessNotFoundException extends OutOfBoundsException implements SwooleServerExceptionInterface
{
}
