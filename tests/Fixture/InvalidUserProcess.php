<?php

declare(strict_types=1);

namespace SuperKernel\Server\Tests\Fixture;

use SuperKernel\Server\Attribute\UserProcess;

#[UserProcess('invalid')]
final class InvalidUserProcess
{
}
