<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Second before-hook spy, to prove before-hooks run in declared order.
 */
final class BeforeHookB implements StateHookInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        RecordingSpy::record('before:B');
    }
}
