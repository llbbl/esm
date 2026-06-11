<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;

/**
 * Guard that records a distinctive label when invoked. Used to prove guard
 * short-circuiting: placed AFTER a rejecting guard, it must NEVER run, so its
 * label must be absent from {@see RecordingSpy}.
 */
final class NeverReachedGuard implements GuardInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool
    {
        RecordingSpy::record('guard:NeverReached');

        return true;
    }
}
