<?php

declare(strict_types=1);

namespace EnumStateMachine\Contracts;

use BackedEnum;

/**
 * A hook performs the work (side-effect) of a transition once it is permitted.
 *
 * `before` hooks run after guards pass and before the state changes; `after`
 * hooks run once the state has changed. Hooks run synchronously, in declared order.
 */
interface StateHookInterface
{
    /**
     * @param BackedEnum $from    The state we are leaving.
     * @param BackedEnum $to      The state we are entering.
     * @param mixed      $context The caller's domain model or payload.
     */
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void;
}
