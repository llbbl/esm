<?php

declare(strict_types=1);

use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Exceptions\InvalidTransitionException;
use EnumStateMachine\Reflection\EnumInspector;
use EnumStateMachine\Tests\Fixtures\CapturingAfterHook;
use EnumStateMachine\Tests\Fixtures\ContextBox;
use EnumStateMachine\Tests\Fixtures\DenyGuard;
use EnumStateMachine\Tests\Fixtures\DocumentState;
use EnumStateMachine\Tests\Fixtures\FakeContainer;
use EnumStateMachine\Tests\Fixtures\FakeDispatcher;
use EnumStateMachine\Tests\Fixtures\HardeningState;
use EnumStateMachine\Tests\Fixtures\Machines;
use EnumStateMachine\Tests\Fixtures\MachineState;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\RecordingSpy;

beforeEach(function (): void {
    EnumInspector::clearCache();
    RecordingSpy::reset();
});

// --- can() when a guard THROWS ----------------------------------------------

it('propagates a guard exception raw from can() and leaves state unchanged', function (): void {
    // MachineState::GuardThrows is gated by ThrowingGuard; can() runs guards, so
    // the throw must surface unwrapped (matching transitionTo) — NOT become false.
    $machine = Machines::machine(MachineState::Start);

    try {
        $machine->can(MachineState::GuardThrows);
        $this->fail('expected the guard exception to propagate from can()');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(RuntimeException::class)
            ->and($e->getMessage())->toBe('guard exploded');
    }

    expect($machine->getCurrentState())->toBe(MachineState::Start);
});

// --- Multi-target transition (to: [A, B]) -----------------------------------

it('allows transitionTo each target listed in an array-form rule, sharing the guard', function (): void {
    // DocumentState::Draft declares #[Transition(to: [Review, Published], guard: NotYetShippedGuard)].
    // Both targets must work and both must consult the SAME guard.
    $toReview = Machines::document(DocumentState::Draft);
    expect($toReview->transitionTo(DocumentState::Review, context: true))
        ->toBe(DocumentState::Review)
        ->and($toReview->getCurrentState())->toBe(DocumentState::Review);

    $toPublished = Machines::document(DocumentState::Draft);
    expect($toPublished->transitionTo(DocumentState::Published, context: true))
        ->toBe(DocumentState::Published)
        ->and($toPublished->getCurrentState())->toBe(DocumentState::Published);
});

it('applies the shared guard of an array-form rule to every listed target', function (): void {
    // NotYetShippedGuard rejects when context === false. The same guard gates
    // BOTH listed targets, so each is vetoed identically.
    $machine = Machines::document(DocumentState::Draft);

    expect($machine->can(DocumentState::Review, context: false))->toBeFalse()
        ->and($machine->can(DocumentState::Published, context: false))->toBeFalse()
        ->and($machine->can(DocumentState::Review, context: true))->toBeTrue()
        ->and($machine->can(DocumentState::Published, context: true))->toBeTrue();
});

// --- Terminal state (no outgoing transitions) -------------------------------

it('rejects every transition out of a terminal state', function (): void {
    // MachineState::Plain has no outgoing case-level rules and MachineState has
    // no wildcard, so it is genuinely terminal.
    $machine = Machines::machine(MachineState::Plain);

    expect($machine->can(MachineState::Ordered))->toBeFalse()
        ->and($machine->can(MachineState::Start))->toBeFalse();

    expect(fn () => $machine->transitionTo(MachineState::Ordered))
        ->toThrow(InvalidTransitionException::class);

    expect($machine->getCurrentState())->toBe(MachineState::Plain);
});

// --- Wildcard through the engine --------------------------------------------

it('drives a wildcard transition (any state -> wildcard target) through the engine', function (): void {
    // DocumentState wildcard: #[Transition(to: Archived, includeSelf: true, before: ReserveInventoryHook)].
    // Review has no case-level rule to Archived, so the wildcard fires.
    $fromReview = Machines::document(DocumentState::Review);
    expect($fromReview->transitionTo(DocumentState::Archived))->toBe(DocumentState::Archived)
        ->and($fromReview->getCurrentState())->toBe(DocumentState::Archived);

    // Published likewise reaches Archived via the wildcard.
    $fromPublished = Machines::document(DocumentState::Published);
    expect($fromPublished->can(DocumentState::Archived))->toBeTrue();
});

it('blocks a wildcard self-loop through the engine unless includeSelf is set', function (): void {
    // OrderState wildcard (-> Cancelled) has includeSelf:false → Cancelled->Cancelled blocked.
    $blocked = Machines::order(OrderState::Cancelled);
    expect($blocked->can(OrderState::Cancelled, context: true))->toBeFalse();
    expect(fn () => $blocked->transitionTo(OrderState::Cancelled, context: true))
        ->toThrow(InvalidTransitionException::class);
    expect($blocked->getCurrentState())->toBe(OrderState::Cancelled);

    // DocumentState wildcard (-> Archived) has includeSelf:true → Archived->Archived allowed.
    $allowed = Machines::document(DocumentState::Archived);
    expect($allowed->can(DocumentState::Archived))->toBeTrue()
        ->and($allowed->transitionTo(DocumentState::Archived))->toBe(DocumentState::Archived);
});

// --- Multiple guards on one transition --------------------------------------

it('throws carrying the SECOND guard class when the first passes and the second rejects', function (): void {
    // HardeningState::MultiGuard is gated by [AllowGuard, DenyGuard].
    $machine = Machines::hardening(HardeningState::Start);

    try {
        $machine->transitionTo(HardeningState::MultiGuard);
        $this->fail('expected InvalidTransitionException');
    } catch (InvalidTransitionException $e) {
        expect($e->getGuard())->toBe(DenyGuard::class)
            ->and($e->getFrom())->toBe(HardeningState::Start)
            ->and($e->getTo())->toBe(HardeningState::MultiGuard);
    }

    // Both guards ran in order, in declared order, and stopped at the rejector.
    expect(RecordingSpy::$calls)->toBe(['guard:Allow', 'guard:Deny'])
        ->and($machine->getCurrentState())->toBe(HardeningState::Start);
});

it('does not consult a later guard once an earlier guard returns false', function (): void {
    // HardeningState::ShortCircuit is gated by [AllowGuard, DenyGuard, NeverReachedGuard].
    // DenyGuard's false must short-circuit so NeverReachedGuard never records.
    $machine = Machines::hardening(HardeningState::Start);

    expect($machine->can(HardeningState::ShortCircuit))->toBeFalse();
    expect(RecordingSpy::$calls)->toBe(['guard:Allow', 'guard:Deny'])
        ->and(RecordingSpy::$calls)->not->toContain('guard:NeverReached');

    RecordingSpy::reset();

    expect(fn () => $machine->transitionTo(HardeningState::ShortCircuit))
        ->toThrow(InvalidTransitionException::class);
    expect(RecordingSpy::$calls)->toBe(['guard:Allow', 'guard:Deny'])
        ->and(RecordingSpy::$calls)->not->toContain('guard:NeverReached');
});

// --- Context identity threaded through before -> after -> event -------------

it('threads one mutated context from a before-hook to the after-hook and the event', function (): void {
    // HardeningState::Mutated: before StampBeforeHook (writes stamp), after
    // CapturingAfterHook (records what it saw). dispatchEvents:true emits the
    // default StateTransitioned carrying the same context.
    $after = new CapturingAfterHook();
    $container = new FakeContainer();
    $container->set(\EnumStateMachine\Tests\Fixtures\StampBeforeHook::class, new \EnumStateMachine\Tests\Fixtures\StampBeforeHook());
    $container->set(CapturingAfterHook::class, $after);

    $dispatcher = new FakeDispatcher();
    $machine = Machines::hardening(HardeningState::Start, $container, $dispatcher);
    $context = new ContextBox();

    expect($context->stamp)->toBeNull();

    $machine->transitionTo(HardeningState::Mutated, context: $context);

    // The before-hook mutated the SAME instance the test still holds.
    expect($context->stamp)->toBe('stamped-by-before');

    // The after-hook saw that mutation on the identical instance.
    expect($after->calls)->toHaveCount(1)
        ->and($after->calls[0]['context'])->toBe($context)
        ->and($after->calls[0]['stamp'])->toBe('stamped-by-before');

    // The dispatched event carries the same mutated context.
    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    expect($event)->toBeInstanceOf(StateTransitioned::class);
    assert($event instanceof StateTransitioned);
    expect($event->context)->toBe($context)
        ->and($event->context)->toBeInstanceOf(ContextBox::class);
    assert($event->context instanceof ContextBox);
    expect($event->context->stamp)->toBe('stamped-by-before');
});
