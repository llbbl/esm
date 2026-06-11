<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;

/**
 * Guard that always vetoes (returns false), exercising the rejection path.
 */
final class DenyGuard implements GuardInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool
    {
        RecordingSpy::record('guard:Deny');

        return false;
    }
}
