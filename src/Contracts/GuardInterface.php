<?php

declare(strict_types=1);

namespace EnumStateMachine\Contracts;

use BackedEnum;

/**
 * A guard decides whether a transition may happen.
 *
 * Guards are pure predicates: they must be side-effect-free and safe to call
 * repeatedly, because {@see \EnumStateMachine\StateMachine::can()} invokes them too.
 */
interface GuardInterface
{
    /**
     * @param BackedEnum $from    The state we are leaving.
     * @param BackedEnum $to      The state we are entering.
     * @param mixed      $context The caller's domain model or payload.
     *
     * @return bool true to allow the transition, false to reject it.
     */
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): bool;
}
