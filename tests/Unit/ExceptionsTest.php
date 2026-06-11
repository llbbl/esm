<?php

declare(strict_types=1);

use EnumStateMachine\Exceptions\HookExecutionException;
use EnumStateMachine\Exceptions\InvalidTransitionException;
use EnumStateMachine\Exceptions\StateMachineException;
use EnumStateMachine\Tests\Fixtures\NotYetShippedGuard;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\ReserveInventoryHook;

it('builds InvalidTransitionException via noRule with from/to and no guard', function (): void {
    $e = InvalidTransitionException::noRule(OrderState::Pending, OrderState::Shipped);

    expect($e)
        ->toBeInstanceOf(StateMachineException::class)
        ->and($e)->toBeInstanceOf(RuntimeException::class)
        ->and($e->getFrom())->toBe(OrderState::Pending)
        ->and($e->getTo())->toBe(OrderState::Shipped)
        ->and($e->getGuard())->toBeNull()
        ->and($e->getMessage())->toContain('OrderState::Pending')
        ->and($e->getMessage())->toContain('OrderState::Shipped');
});

it('builds InvalidTransitionException via rejectedByGuard carrying the guard class', function (): void {
    $e = InvalidTransitionException::rejectedByGuard(
        OrderState::Pending,
        OrderState::Cancelled,
        NotYetShippedGuard::class,
    );

    expect($e->getFrom())->toBe(OrderState::Pending)
        ->and($e->getTo())->toBe(OrderState::Cancelled)
        ->and($e->getGuard())->toBe(NotYetShippedGuard::class)
        ->and($e->getMessage())->toContain(NotYetShippedGuard::class);
});

it('builds HookExecutionException via fromHook carrying from/to/hook and chaining previous', function (): void {
    $previous = new RuntimeException('mailer down');

    $e = HookExecutionException::fromHook(
        ReserveInventoryHook::class,
        OrderState::Paid,
        OrderState::Shipped,
        $previous,
    );

    expect($e)->toBeInstanceOf(StateMachineException::class)
        ->and($e->getFrom())->toBe(OrderState::Paid)
        ->and($e->getTo())->toBe(OrderState::Shipped)
        ->and($e->getHook())->toBe(ReserveInventoryHook::class)
        ->and($e->getPrevious())->toBe($previous)
        ->and($e->getMessage())->toContain('mailer down')
        ->and($e->getMessage())->toContain(ReserveInventoryHook::class);
});

it('lets all engine exceptions be caught as the base StateMachineException', function (): void {
    $invalid = InvalidTransitionException::noRule(OrderState::Pending, OrderState::Shipped);
    $hook = HookExecutionException::fromHook(
        ReserveInventoryHook::class,
        OrderState::Paid,
        OrderState::Shipped,
        new RuntimeException('boom'),
    );

    expect($invalid)->toBeInstanceOf(StateMachineException::class)
        ->and($hook)->toBeInstanceOf(StateMachineException::class);
});
