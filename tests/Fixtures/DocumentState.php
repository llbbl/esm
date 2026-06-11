<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\Attributes\Transition;

/**
 * Richer fixture exercising EnumInspector edge cases that OrderState does not:
 *
 *  - array-form `to` sharing one guard/hook set across several targets;
 *  - two case-level rules targeting the SAME case to prove first-declared-wins;
 *  - an `includeSelf: true` wildcard self-loop on every case;
 *  - a terminal case (`Archived`) with no outgoing case-level transitions.
 *
 * No #[StateMachineConfig] is declared, so config() must return the default
 * (dispatchEvents: true, event: null).
 */
#[Transition(to: self::Archived, includeSelf: true, before: ReserveInventoryHook::class)]
enum DocumentState: string
{
    // Array-form target list: one rule, multiple permitted targets.
    #[Transition(to: [self::Review, self::Published], guard: NotYetShippedGuard::class)]
    // Case-level override of the wildcard `-> Archived`: same target, NO hooks,
    // so precedence over the wildcard (which has a before hook) is provable.
    #[Transition(to: self::Archived)]
    case Draft = 'draft';

    // Two case-level rules to the SAME target (Published): first wins.
    #[Transition(to: self::Published, before: ReserveInventoryHook::class)]
    #[Transition(to: self::Published, after: ReserveInventoryHook::class)]
    case Review = 'review';

    case Published = 'published';

    // Terminal: no outgoing case-level transitions; only the wildcard self-loop applies.
    case Archived = 'archived';
}
