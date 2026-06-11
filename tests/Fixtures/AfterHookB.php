<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Second after-hook spy, to prove after-hooks run in declared order and that a
 * preceding throwing after-hook skips this one.
 */
final class AfterHookB implements StateHookInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        RecordingSpy::record('after:B');
    }
}
