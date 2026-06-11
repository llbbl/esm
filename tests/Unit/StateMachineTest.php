<?php

declare(strict_types=1);

use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Exceptions\HookExecutionException;
use EnumStateMachine\Exceptions\InvalidTransitionException;
use EnumStateMachine\Exceptions\StateMachineException;
use EnumStateMachine\Reflection\EnumInspector;
use EnumStateMachine\Tests\Fixtures\AfterHookA;
use EnumStateMachine\Tests\Fixtures\AllowGuard;
use EnumStateMachine\Tests\Fixtures\CustomEvent;
use EnumStateMachine\Tests\Fixtures\CustomEventState;
use EnumStateMachine\Tests\Fixtures\FakeContainer;
use EnumStateMachine\Tests\Fixtures\FakeDispatcher;
use EnumStateMachine\Tests\Fixtures\Machines;
use EnumStateMachine\Tests\Fixtures\MachineState;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\RecordingSpy;

beforeEach(function (): void {
    EnumInspector::clearCache();
    RecordingSpy::reset();
});

// --- getCurrentState / mutability -------------------------------------------

it('reports the initial state', function (): void {
    $machine = Machines::machine(MachineState::Start);
    expect($machine->getCurrentState())->toBe(MachineState::Start);
});

it('reflects the mutated state after a successful transition', function (): void {
    $machine = Machines::machine(MachineState::Start);
    $returned = $machine->transitionTo(MachineState::Plain);

    expect($returned)->toBe(MachineState::Plain)
        ->and($machine->getCurrentState())->toBe(MachineState::Plain);
});

// --- can() ------------------------------------------------------------------

it('can() is true for a bare edge with no guards', function (): void {
    $machine = Machines::machine(MachineState::Start);
    expect($machine->can(MachineState::Plain))->toBeTrue();
});

it('can() is true when the edge exists and every guard passes', function (): void {
    $machine = Machines::machine(MachineState::Start);
    expect($machine->can(MachineState::Ordered))->toBeTrue();
});

it('can() is false when no rule matches', function (): void {
    $machine = Machines::machine(MachineState::Start);
    // Plain is terminal here: no outgoing edges.
    $machine = Machines::machine(MachineState::Plain);
    expect($machine->can(MachineState::Ordered))->toBeFalse();
});

it('can() is false when a guard rejects', function (): void {
    $machine = Machines::machine(MachineState::Start);
    expect($machine->can(MachineState::Denied))->toBeFalse();
});

it('can() passes the correct from, to and context to guards', function (): void {
    $machine = Machines::machine(MachineState::Start);
    $context = new stdClass();

    expect($machine->can(MachineState::Ordered, $context))->toBeTrue();

    // AllowGuard is built fresh per resolution; assert via the recorded sequence
    // and a dedicated container-backed instance below. Here we re-run with a
    // container so we can inspect the captured call.
    $guard = new AllowGuard();
    $container = new FakeContainer();
    $container->set(AllowGuard::class, $guard);

    $machine = Machines::machine(MachineState::Start, $container);
    $machine->can(MachineState::Ordered, $context);

    expect($guard->calls)->toHaveCount(1)
        ->and($guard->calls[0]['from'])->toBe(MachineState::Start)
        ->and($guard->calls[0]['to'])->toBe(MachineState::Ordered)
        ->and($guard->calls[0]['context'])->toBe($context);
});

it('can() does not run before or after hooks', function (): void {
    $machine = Machines::machine(MachineState::Start);
    $machine->can(MachineState::Ordered);

    expect(RecordingSpy::$calls)->toBe(['guard:Allow']);
});

// --- Happy path: ordering + event -------------------------------------------

it('runs guards, before-hooks, mutation, then after-hooks in declared order', function (): void {
    $dispatcher = new FakeDispatcher();
    $machine = Machines::machine(MachineState::Start, dispatcher: $dispatcher);
    $context = new stdClass();

    $machine->transitionTo(MachineState::Ordered, $context);

    expect(RecordingSpy::$calls)->toBe([
        'guard:Allow',
        'before:A',
        'before:B',
        'after:A',
        'after:B',
    ]);
});

it('dispatches a StateTransitioned event carrying from, to and context', function (): void {
    $dispatcher = new FakeDispatcher();
    $machine = Machines::machine(MachineState::Start, dispatcher: $dispatcher);
    $context = new stdClass();

    $machine->transitionTo(MachineState::Ordered, $context);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    expect($event)->toBeInstanceOf(StateTransitioned::class);
    assert($event instanceof StateTransitioned);

    expect($event->from)->toBe(MachineState::Start)
        ->and($event->to)->toBe(MachineState::Ordered)
        ->and($event->context)->toBe($context);
});

it('passes the correct from, to and context to before- and after-hooks', function (): void {
    $before = new \EnumStateMachine\Tests\Fixtures\BeforeHookA();
    $after = new AfterHookA();
    $container = new FakeContainer();
    $container->set(\EnumStateMachine\Tests\Fixtures\BeforeHookA::class, $before);
    $container->set(AfterHookA::class, $after);
    // Remaining classes still resolved via container, so bind them too.
    $container->set(AllowGuard::class, new AllowGuard());
    $container->set(\EnumStateMachine\Tests\Fixtures\BeforeHookB::class, new \EnumStateMachine\Tests\Fixtures\BeforeHookB());
    $container->set(\EnumStateMachine\Tests\Fixtures\AfterHookB::class, new \EnumStateMachine\Tests\Fixtures\AfterHookB());

    $machine = Machines::machine(MachineState::Start, $container);
    $context = new stdClass();

    $machine->transitionTo(MachineState::Ordered, $context);

    expect($before->calls[0]['from'])->toBe(MachineState::Start)
        ->and($before->calls[0]['to'])->toBe(MachineState::Ordered)
        ->and($before->calls[0]['context'])->toBe($context)
        ->and($after->calls[0]['from'])->toBe(MachineState::Start)
        ->and($after->calls[0]['to'])->toBe(MachineState::Ordered)
        ->and($after->calls[0]['context'])->toBe($context);
});

// --- No rule ----------------------------------------------------------------

it('throws InvalidTransitionException when no rule matches', function (): void {
    $machine = Machines::machine(MachineState::Plain);
    expect(fn () => $machine->transitionTo(MachineState::Ordered))
        ->toThrow(InvalidTransitionException::class);
});

it('the no-rule exception carries from and to and leaves state unchanged', function (): void {
    $machine = Machines::machine(MachineState::Plain);
    try {
        $machine->transitionTo(MachineState::Ordered);
        $this->fail('expected InvalidTransitionException');
    } catch (InvalidTransitionException $e) {
        expect($e->getFrom())->toBe(MachineState::Plain)
            ->and($e->getTo())->toBe(MachineState::Ordered)
            ->and($e->getGuard())->toBeNull();
    }

    expect($machine->getCurrentState())->toBe(MachineState::Plain);
});

// --- Guard rejects ----------------------------------------------------------

it('throws InvalidTransitionException carrying the guard class when a guard returns false', function (): void {
    $machine = Machines::machine(MachineState::Start);
    try {
        $machine->transitionTo(MachineState::Denied);
        $this->fail('expected InvalidTransitionException');
    } catch (InvalidTransitionException $e) {
        expect($e->getGuard())->toBe(\EnumStateMachine\Tests\Fixtures\DenyGuard::class)
            ->and($e->getFrom())->toBe(MachineState::Start)
            ->and($e->getTo())->toBe(MachineState::Denied);
    }

    expect($machine->getCurrentState())->toBe(MachineState::Start);
});

// --- Guard throws -----------------------------------------------------------

it('propagates a guard exception raw and leaves state unchanged', function (): void {
    $machine = Machines::machine(MachineState::Start);
    try {
        $machine->transitionTo(MachineState::GuardThrows);
        $this->fail('expected RuntimeException');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(RuntimeException::class)
            ->and($e)->not->toBeInstanceOf(StateMachineException::class)
            ->and($e->getMessage())->toBe('guard exploded');
    }

    expect($machine->getCurrentState())->toBe(MachineState::Start);
});

// --- Before-hook throws -----------------------------------------------------

it('propagates a before-hook exception raw, leaves state unchanged, and skips after-hooks', function (): void {
    $dispatcher = new FakeDispatcher();
    $machine = Machines::machine(MachineState::Start, dispatcher: $dispatcher);
    try {
        $machine->transitionTo(MachineState::BeforeThrows);
        $this->fail('expected RuntimeException');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(RuntimeException::class)
            ->and($e)->not->toBeInstanceOf(StateMachineException::class)
            ->and($e->getMessage())->toBe('before exploded');
    }

    // First before-hook threw; the second never ran.
    expect(RecordingSpy::$calls)->toBe(['before:Throwing'])
        ->and($machine->getCurrentState())->toBe(MachineState::Start)
        ->and($dispatcher->events)->toBe([]);
});

// --- After-hook throws ------------------------------------------------------

it('wraps an after-hook throwable, keeps the state changed, skips later hooks, and emits no event', function (): void {
    $dispatcher = new FakeDispatcher();
    $machine = Machines::machine(MachineState::Start, dispatcher: $dispatcher);
    try {
        $machine->transitionTo(MachineState::AfterThrows);
        $this->fail('expected HookExecutionException');
    } catch (HookExecutionException $e) {
        expect($e->getHook())->toBe(\EnumStateMachine\Tests\Fixtures\ThrowingAfterHook::class)
            ->and($e->getFrom())->toBe(MachineState::Start)
            ->and($e->getTo())->toBe(MachineState::AfterThrows)
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class)
            ->and($e->getPrevious()?->getMessage())->toBe('after exploded');
    }

    // State change stands; the second after-hook (after:B) was skipped.
    expect($machine->getCurrentState())->toBe(MachineState::AfterThrows)
        ->and(RecordingSpy::$calls)->toBe(['after:Throwing'])
        ->and($dispatcher->events)->toBe([]);
});

// --- Container resolution ----------------------------------------------------

it('resolves guards and hooks through the container when one is supplied', function (): void {
    $container = new FakeContainer();
    $container->set(AllowGuard::class, new AllowGuard());
    $container->set(\EnumStateMachine\Tests\Fixtures\BeforeHookA::class, new \EnumStateMachine\Tests\Fixtures\BeforeHookA());
    $container->set(\EnumStateMachine\Tests\Fixtures\BeforeHookB::class, new \EnumStateMachine\Tests\Fixtures\BeforeHookB());
    $container->set(AfterHookA::class, new AfterHookA());
    $container->set(\EnumStateMachine\Tests\Fixtures\AfterHookB::class, new \EnumStateMachine\Tests\Fixtures\AfterHookB());

    $machine = Machines::machine(MachineState::Start, $container);
    $machine->transitionTo(MachineState::Ordered);

    expect($container->requested)->toBe([
        AllowGuard::class,
        \EnumStateMachine\Tests\Fixtures\BeforeHookA::class,
        \EnumStateMachine\Tests\Fixtures\BeforeHookB::class,
        AfterHookA::class,
        \EnumStateMachine\Tests\Fixtures\AfterHookB::class,
    ]);
});

it('uses new $class() (not the container) when none is supplied', function (): void {
    // Without a container the transition still succeeds, proving direct
    // instantiation of the no-arg fixture classes.
    $machine = Machines::machine(MachineState::Start);
    $machine->transitionTo(MachineState::Ordered);

    expect($machine->getCurrentState())->toBe(MachineState::Ordered);
});

// --- Interface conformance ---------------------------------------------------

it('throws StateMachineException when a resolved guard does not implement GuardInterface', function (): void {
    // The container is the realistic place a wrong-typed object can appear: bind
    // the AllowGuard key to a class that is NOT a GuardInterface.
    $container = new FakeContainer();
    $container->set(AllowGuard::class, new \EnumStateMachine\Tests\Fixtures\NotAGuard());

    $machine = Machines::machine(MachineState::Start, $container);
    expect(fn () => $machine->transitionTo(MachineState::Ordered))
        ->toThrow(StateMachineException::class, 'must implement');
});

// --- Event gating ------------------------------------------------------------

it('does not dispatch an event when no dispatcher is supplied', function (): void {
    // No dispatcher; transition still completes without error.
    $machine = Machines::machine(MachineState::Start);
    $machine->transitionTo(MachineState::Plain);

    expect($machine->getCurrentState())->toBe(MachineState::Plain);
});

it('does not dispatch an event when dispatchEvents is false even with a dispatcher', function (): void {
    // OrderState declares dispatchEvents: false.
    $dispatcher = new FakeDispatcher();
    $machine = Machines::order(OrderState::Pending, dispatcher: $dispatcher);
    $machine->transitionTo(OrderState::Paid);

    expect($machine->getCurrentState())->toBe(OrderState::Paid)
        ->and($dispatcher->events)->toBe([]);
});

it('honors a custom event class from config', function (): void {
    $dispatcher = new FakeDispatcher();
    $machine = Machines::customEvent(CustomEventState::Start, dispatcher: $dispatcher);
    $context = new stdClass();

    $machine->transitionTo(CustomEventState::Done, $context);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    expect($event)->toBeInstanceOf(CustomEvent::class);
    assert($event instanceof CustomEvent);

    expect($event->from)->toBe(CustomEventState::Start)
        ->and($event->to)->toBe(CustomEventState::Done)
        ->and($event->context)->toBe($context);
});
