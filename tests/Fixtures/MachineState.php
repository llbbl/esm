<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;

/**
 * Primary fixture for the StateMachine engine tests.
 *
 * `dispatchEvents: true` with the DEFAULT event (no `event:` override), so the
 * engine emits {@see \EnumStateMachine\Events\StateTransitioned} when a
 * dispatcher is present.
 *
 * Edges (all from `Start`) cover the engine's branches:
 *   - Ordered     : two guards, two before-hooks, two after-hooks — proves
 *                   guard → before → (mutate) → after ordering and per-bucket order.
 *   - Denied      : a guard that returns false (veto path).
 *   - GuardThrows : a guard that throws (raw propagation).
 *   - BeforeThrows: a before-hook that throws (raw propagation, state unchanged).
 *   - AfterThrows : two after-hooks, the first throws (wrap + skip rest).
 *   - Plain       : a bare edge with no guards/hooks (clean happy path / event).
 */
#[StateMachineConfig(dispatchEvents: true)]
enum MachineState: string
{
    #[Transition(to: self::Ordered, guard: [AllowGuard::class], before: [BeforeHookA::class, BeforeHookB::class], after: [AfterHookA::class, AfterHookB::class])]
    #[Transition(to: self::Denied, guard: DenyGuard::class)]
    #[Transition(to: self::GuardThrows, guard: ThrowingGuard::class)]
    #[Transition(to: self::BeforeThrows, before: [ThrowingBeforeHook::class, BeforeHookA::class])]
    #[Transition(to: self::AfterThrows, after: [ThrowingAfterHook::class, AfterHookB::class])]
    #[Transition(to: self::Plain)]
    case Start = 'start';

    case Ordered = 'ordered';
    case Denied = 'denied';
    case GuardThrows = 'guard_throws';
    case BeforeThrows = 'before_throws';
    case AfterThrows = 'after_throws';
    case Plain = 'plain';
}
