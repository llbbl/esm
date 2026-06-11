<?php

declare(strict_types=1);

use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Tests\Fixtures\OrderState;

it('holds its from, to and context values', function (): void {
    $context = new stdClass();
    $event = new StateTransitioned(OrderState::Paid, OrderState::Shipped, $context);

    expect($event->from)->toBe(OrderState::Paid)
        ->and($event->to)->toBe(OrderState::Shipped)
        ->and($event->context)->toBe($context);
});

it('accepts a null context', function (): void {
    $event = new StateTransitioned(OrderState::Pending, OrderState::Cancelled, null);

    expect($event->from)->toBe(OrderState::Pending)
        ->and($event->to)->toBe(OrderState::Cancelled)
        ->and($event->context)->toBeNull();
});
