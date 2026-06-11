<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;
use RuntimeException;

/**
 * Guard that throws, exercising the raw-propagation path (the engine must not
 * catch or wrap a guard's exception).
 */
final class ThrowingGuard implements GuardInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool
    {
        RecordingSpy::record('guard:Throwing');

        throw new RuntimeException('guard exploded');
    }
}
