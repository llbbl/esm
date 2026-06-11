<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;

/**
 * A custom transition event honoring the §4.5 contract: constructable with
 * `($from, $to, $context)`. Used to prove `StateMachineConfig::$event` overrides
 * the default {@see \EnumStateMachine\Events\StateTransitioned}.
 */
final class CustomEvent
{
    public function __construct(
        public readonly BackedEnum $from,
        public readonly BackedEnum $to,
        public readonly mixed $context,
    ) {
    }
}
