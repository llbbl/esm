<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;

/**
 * Guard that always allows. Records into {@see RecordingSpy} (so call order
 * relative to hooks is observable) and captures its `($from, $to, $context)` so
 * tests can assert the engine passes them through. Recording is a test
 * affordance only; production guards are expected to be side-effect-free.
 */
final class AllowGuard implements GuardInterface
{
    /** @var list<array{from: BackedEnum, to: BackedEnum, context: mixed}> */
    public array $calls = [];

    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool
    {
        RecordingSpy::record('guard:Allow');
        $this->calls[] = ['from' => $from, 'to' => $to, 'context' => $context];

        return true;
    }
}
