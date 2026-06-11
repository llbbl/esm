<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;

/**
 * Sample backed enum used to exercise attribute reflection.
 *
 * `Pending` carries two repeated #[Transition] attributes to prove IS_REPEATABLE.
 */
#[StateMachineConfig(dispatchEvents: false, event: \EnumStateMachine\Events\StateTransitioned::class)]
#[Transition(to: self::Cancelled, guard: NotYetShippedGuard::class)]
enum OrderState: string
{
    #[Transition(to: self::Paid, before: ReserveInventoryHook::class)]
    #[Transition(to: self::Cancelled, guard: NotYetShippedGuard::class, includeSelf: true)]
    case Pending = 'pending';

    #[Transition(
        to: self::Shipped,
        guard: NotYetShippedGuard::class,
        before: ReserveInventoryHook::class,
        after: ReserveInventoryHook::class,
    )]
    case Paid = 'paid';

    case Shipped = 'shipped';

    case Cancelled = 'cancelled';
}
