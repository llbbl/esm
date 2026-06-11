<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Trivial hook fixture: records each invocation so tests can assert it ran with
 * the expected signature.
 */
final class ReserveInventoryHook implements StateHookInterface
{
    /** @var list<array{from: BackedEnum, to: BackedEnum, context: mixed}> */
    public array $calls = [];

    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        $this->calls[] = ['from' => $from, 'to' => $to, 'context' => $context];
    }
}
