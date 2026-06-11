<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * First before-hook spy: records its order via {@see RecordingSpy} and captures
 * the `($from, $to, $context)` it received.
 */
final class BeforeHookA implements StateHookInterface
{
    /** @var list<array{from: BackedEnum, to: BackedEnum, context: mixed}> */
    public array $calls = [];

    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        RecordingSpy::record('before:A');
        $this->calls[] = ['from' => $from, 'to' => $to, 'context' => $context];
    }
}
