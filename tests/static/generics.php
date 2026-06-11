<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\StaticAnalysis;

use EnumStateMachine\StateMachine;
use EnumStateMachine\Tests\Fixtures\OrderState;

use function PHPStan\Testing\assertType;

/**
 * Static-analysis fixture for the `@template T of \BackedEnum` generics on
 * {@see StateMachine} (§5.7).
 *
 * This file proves the DOCUMENTED consumer pattern narrows correctly: the
 * machine is built directly from an enum-typed value (a parameter typed
 * `OrderState`, mirroring a model's typed `$order->state` property), NOT via the
 * test-only {@see Machines} factory and NOT from a bare case literal. That is the
 * exact construction the README and spec §5.7/§6 tell consumers to use, so this
 * fixture verifies the type narrowing real callers depend on.
 *
 * This file is NEVER executed: it lives outside the PHPUnit/Pest testsuite
 * (excluded in phpunit.xml) and exists purely so `just stan` proves the enum
 * narrowing holds. The `\PHPStan\Testing\assertType()` calls are understood by
 * PHPStan at analysis time (no runtime autoloading needed); the typed-function
 * consumers below would raise `argument.type` / `assign.propertyType` errors if
 * the generics ever regressed to the bare `\BackedEnum`, so a broken `@template`
 * breaks the build instead of silently degrading IDE/PHPStan support.
 */

/**
 * Consumer typed to the CONCRETE enum: only compiles if the engine narrows
 * `transitionTo()` / `getCurrentState()` back to `OrderState`. Reading the
 * backed `->value` also requires the concrete enum (bare `\BackedEnum` would
 * still expose `value`, but the parameter type is the load-bearing assertion).
 */
function persistOrderState(OrderState $state): string
{
    return $state->value;
}

/**
 * The realistic consumer flow the spec documents (§4.2, §6): the starting state
 * is an enum-typed value (e.g. a model property `$order->state`), NOT a bare
 * literal case. PHPStan binds `T` to `OrderState` and narrows everything below.
 *
 * @return list<string>
 */
function exerciseNarrowing(OrderState $current): array
{
    $machine = new StateMachine($current);

    // getCurrentState() narrows to the concrete enum, not bare \BackedEnum.
    assertType(OrderState::class, $machine->getCurrentState());

    // transitionTo() accepts the same enum and returns the concrete enum.
    $next = $machine->transitionTo(OrderState::Cancelled);
    assertType(OrderState::class, $next);

    // can() shares the same `@param T`; the target is the concrete enum.
    $allowed = $machine->can(OrderState::Cancelled);

    // Hard regression tripwires: these calls only type-check while the generics
    // hold. If `transitionTo()` reverted to returning \BackedEnum, or
    // getCurrentState() lost `T`, PHPStan would flag argument.type here. The
    // results are consumed so the calls are not flagged as effectless.
    return [
        persistOrderState($next),
        persistOrderState($machine->getCurrentState()),
        $allowed ? persistOrderState($current) : '',
    ];
}
