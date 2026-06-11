<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;
use RuntimeException;

/**
 * Before-hook that throws, exercising raw propagation with the state unchanged.
 */
final class ThrowingBeforeHook implements StateHookInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        RecordingSpy::record('before:Throwing');

        throw new RuntimeException('before exploded');
    }
}
