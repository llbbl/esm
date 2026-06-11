<?php

declare(strict_types=1);

use EnumStateMachine\Contracts\GuardInterface;
use EnumStateMachine\Contracts\StateHookInterface;
use EnumStateMachine\Tests\Fixtures\NotYetShippedGuard;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\ReserveInventoryHook;

it('invokes a guard fixture with the interface signature and returns a bool', function (): void {
    $guard = new NotYetShippedGuard();

    expect($guard)->toBeInstanceOf(GuardInterface::class)
        ->and($guard(OrderState::Pending, OrderState::Cancelled, new stdClass()))->toBeTrue()
        ->and($guard(OrderState::Pending, OrderState::Cancelled, false))->toBeFalse();
});

it('invokes a hook fixture with the interface signature and records the call', function (): void {
    $hook = new ReserveInventoryHook();
    $context = new stdClass();

    expect($hook)->toBeInstanceOf(StateHookInterface::class);

    $hook(OrderState::Paid, OrderState::Shipped, $context);

    expect($hook->calls)->toHaveCount(1)
        ->and($hook->calls[0]['from'])->toBe(OrderState::Paid)
        ->and($hook->calls[0]['to'])->toBe(OrderState::Shipped)
        ->and($hook->calls[0]['context'])->toBe($context);
});
