<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;

/**
 * Trivial guard fixture: allows the transition unless the context is the
 * boolean `false` (a convenient lever for tests).
 */
final class NotYetShippedGuard implements GuardInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool
    {
        return $context !== false;
    }
}
