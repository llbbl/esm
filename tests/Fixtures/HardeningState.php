<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;

/**
 * Fixture for the Unit 4 (hardening) engine edge-case tests.
 *
 * `dispatchEvents: true` with the DEFAULT event so the context-mutation edge can
 * assert the dispatched {@see \EnumStateMachine\Events\StateTransitioned} carries
 * the same (mutated) context instance.
 *
 * Edges (all from `Start`):
 *   - MultiGuard   : guard [Allow, Deny] — first passes, second rejects. Proves
 *                    the exception carries the SECOND guard's class.
 *   - ShortCircuit : guard [Allow, Deny, NeverReached] — proves a guard after the
 *                    first `false` is never consulted (NeverReachedGuard silent).
 *   - Mutated      : before StampBeforeHook (mutates the ContextBox), after
 *                    CapturingAfterHook (records what it saw). Proves the same
 *                    context threads through before → after → event.
 */
#[StateMachineConfig(dispatchEvents: true)]
enum HardeningState: string
{
    #[Transition(to: self::MultiGuard, guard: [AllowGuard::class, DenyGuard::class])]
    #[Transition(to: self::ShortCircuit, guard: [AllowGuard::class, DenyGuard::class, NeverReachedGuard::class])]
    #[Transition(to: self::Mutated, before: StampBeforeHook::class, after: CapturingAfterHook::class)]
    case Start = 'start';

    case MultiGuard = 'multi_guard';
    case ShortCircuit = 'short_circuit';
    case Mutated = 'mutated';
}
