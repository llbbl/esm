<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;
use RuntimeException;

/**
 * After-hook that throws: the engine wraps it in HookExecutionException, keeps
 * the state change, and skips any later after-hooks.
 */
final class ThrowingAfterHook implements StateHookInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        RecordingSpy::record('after:Throwing');

        throw new RuntimeException('after exploded');
    }
}
