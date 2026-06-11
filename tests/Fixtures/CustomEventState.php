<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;

/**
 * Fixture proving `StateMachineConfig::$event` overrides the default event class:
 * `dispatchEvents: true` with a custom `event: CustomEvent::class`.
 */
#[StateMachineConfig(dispatchEvents: true, event: CustomEvent::class)]
enum CustomEventState: string
{
    #[Transition(to: self::Done)]
    case Start = 'start';

    case Done = 'done';
}
