<?php

declare(strict_types=1);

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;
use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Tests\Fixtures\NotYetShippedGuard;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\ReserveInventoryHook;

it('declares the correct attribute targets and repeatability on Transition', function (): void {
    $reflection = new ReflectionClass(Transition::class);
    $attrs = $reflection->getAttributes(Attribute::class);

    expect($attrs)->toHaveCount(1);

    /** @var Attribute $meta */
    $meta = $attrs[0]->newInstance();

    $expected = Attribute::TARGET_CLASS
        | Attribute::TARGET_CLASS_CONSTANT
        | Attribute::IS_REPEATABLE;

    expect($meta->flags)->toBe($expected)
        ->and($reflection->isFinal())->toBeTrue();
});

it('declares TARGET_CLASS on StateMachineConfig', function (): void {
    $reflection = new ReflectionClass(StateMachineConfig::class);
    $attrs = $reflection->getAttributes(Attribute::class);

    /** @var Attribute $meta */
    $meta = $attrs[0]->newInstance();

    expect($meta->flags)->toBe(Attribute::TARGET_CLASS)
        ->and($reflection->isFinal())->toBeTrue();
});

it('reads the class-level wildcard Transition off the enum', function (): void {
    $reflection = new ReflectionEnum(OrderState::class);
    $attrs = $reflection->getAttributes(Transition::class);

    expect($attrs)->toHaveCount(1);

    /** @var Transition $transition */
    $transition = $attrs[0]->newInstance();

    expect($transition->to)->toBe(OrderState::Cancelled)
        ->and($transition->guard)->toBe(NotYetShippedGuard::class)
        ->and($transition->before)->toBeNull()
        ->and($transition->after)->toBeNull()
        ->and($transition->includeSelf)->toBeFalse();
});

it('reads two repeated Transition attributes off a single case (IS_REPEATABLE)', function (): void {
    $reflection = new ReflectionEnum(OrderState::class);
    $case = $reflection->getCase('Pending');
    $attrs = $case->getAttributes(Transition::class);

    expect($attrs)->toHaveCount(2);

    /** @var Transition $first */
    $first = $attrs[0]->newInstance();
    /** @var Transition $second */
    $second = $attrs[1]->newInstance();

    expect($first->to)->toBe(OrderState::Paid)
        ->and($first->before)->toBe(ReserveInventoryHook::class)
        ->and($first->includeSelf)->toBeFalse()
        ->and($second->to)->toBe(OrderState::Cancelled)
        ->and($second->guard)->toBe(NotYetShippedGuard::class)
        ->and($second->includeSelf)->toBeTrue();
});

it('reads the StateMachineConfig attribute off the enum', function (): void {
    $reflection = new ReflectionEnum(OrderState::class);
    $attrs = $reflection->getAttributes(StateMachineConfig::class);

    expect($attrs)->toHaveCount(1);

    /** @var StateMachineConfig $config */
    $config = $attrs[0]->newInstance();

    expect($config->dispatchEvents)->toBeFalse()
        ->and($config->event)->toBe(StateTransitioned::class);
});

it('applies StateMachineConfig defaults when constructed without arguments', function (): void {
    $config = new StateMachineConfig();

    expect($config->dispatchEvents)->toBeTrue()
        ->and($config->event)->toBeNull();
});
