<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use EnumStateMachine\StateMachine;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Test-only factories that construct a {@see StateMachine} for a SPECIFIC enum
 * fixture and hand back a `StateMachine<ThatEnum>`.
 *
 * Why this exists (static analysis, §5.7): the engine is generic over
 * `@template T of \BackedEnum`. When PHPStan binds `T` from a bare enum-case
 * literal passed straight to `new StateMachine(MachineState::Start)`, it narrows
 * `T` to the literal CASE type (`MachineState::Start`) rather than the enum
 * (`MachineState`) — so a later `transitionTo(MachineState::Plain)` would be
 * flagged. That literal-case binding is intrinsic PHPStan behaviour and cannot
 * be widened from the class side without an inline `@var` (which is forbidden).
 *
 * Each factory below takes its starting state typed as the concrete ENUM (not a
 * literal case) and declares an explicit `@return StateMachine<TheEnum>`, so
 * PHPStan binds `T` to the whole enum. These factories model the documented
 * enum-typed-value construction — they mirror `new StateMachine($model->state, ...)`,
 * where `$state` is an enum-typed model property, not a literal — so they are NOT
 * masking the literal-case footgun: a bare-literal call would narrow `T` here too.
 * This lets the existing engine tests keep passing arbitrary target cases while
 * the generics stay strict.
 *
 * Runtime behaviour is identical to calling the constructor directly.
 */
final class Machines
{
    /**
     * @return StateMachine<MachineState>
     */
    public static function machine(
        MachineState $start,
        ?ContainerInterface $container = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): StateMachine {
        return new StateMachine($start, $container, $dispatcher);
    }

    /**
     * @return StateMachine<OrderState>
     */
    public static function order(
        OrderState $start,
        ?ContainerInterface $container = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): StateMachine {
        return new StateMachine($start, $container, $dispatcher);
    }

    /**
     * @return StateMachine<CustomEventState>
     */
    public static function customEvent(
        CustomEventState $start,
        ?ContainerInterface $container = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): StateMachine {
        return new StateMachine($start, $container, $dispatcher);
    }

    /**
     * @return StateMachine<HardeningState>
     */
    public static function hardening(
        HardeningState $start,
        ?ContainerInterface $container = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): StateMachine {
        return new StateMachine($start, $container, $dispatcher);
    }

    /**
     * @return StateMachine<DocumentState>
     */
    public static function document(
        DocumentState $start,
        ?ContainerInterface $container = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): StateMachine {
        return new StateMachine($start, $container, $dispatcher);
    }
}
