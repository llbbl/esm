<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Unit;

use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Reflection\EnumInspector;
use EnumStateMachine\Reflection\ResolvedTransition;
use EnumStateMachine\Tests\Fixtures\DocumentState;
use EnumStateMachine\Tests\Fixtures\NotYetShippedGuard;
use EnumStateMachine\Tests\Fixtures\OrderState;
use EnumStateMachine\Tests\Fixtures\ReserveInventoryHook;

// Keep every test deterministic and isolated from process-static cache leakage.
beforeEach(function (): void {
    EnumInspector::clearCache();
});

/*
|--------------------------------------------------------------------------
| Case-level resolution & normalization
|--------------------------------------------------------------------------
*/

it('resolves a case-level transition and normalizes single-string hooks to lists', function (): void {
    $resolved = (new EnumInspector())->resolve(OrderState::Pending, OrderState::Paid);

    expect($resolved)->toBeInstanceOf(ResolvedTransition::class);
    assert($resolved !== null);
    expect($resolved->to())->toBe(OrderState::Paid);
    expect($resolved->isWildcard())->toBeFalse();
    expect($resolved->guards())->toBe([]);
    expect($resolved->before())->toBe([ReserveInventoryHook::class]);
    expect($resolved->after())->toBe([]);
});

it('resolves a case-level transition carrying guard + before + after', function (): void {
    $resolved = (new EnumInspector())->resolve(OrderState::Paid, OrderState::Shipped);

    assert($resolved !== null);
    expect($resolved->isWildcard())->toBeFalse();
    expect($resolved->guards())->toBe([NotYetShippedGuard::class]);
    expect($resolved->before())->toBe([ReserveInventoryHook::class]);
    expect($resolved->after())->toBe([ReserveInventoryHook::class]);
});

it('normalizes an array-form `to` so every listed target matches the same rule', function (): void {
    $inspector = new EnumInspector();

    $toReview = $inspector->resolve(DocumentState::Draft, DocumentState::Review);
    $toPublished = $inspector->resolve(DocumentState::Draft, DocumentState::Published);

    assert($toReview !== null);
    expect($toReview->to())->toBe(DocumentState::Review);
    expect($toReview->guards())->toBe([NotYetShippedGuard::class]);

    assert($toPublished !== null);
    expect($toPublished->to())->toBe(DocumentState::Published);
    expect($toPublished->guards())->toBe([NotYetShippedGuard::class]);
});

/*
|--------------------------------------------------------------------------
| Wildcard resolution & self-loops
|--------------------------------------------------------------------------
*/

it('resolves a wildcard transition from a case with no matching case-level rule', function (): void {
    // Shipped has no case-level rules; the class-level wildcard (-> Cancelled) applies.
    $resolved = (new EnumInspector())->resolve(OrderState::Shipped, OrderState::Cancelled);

    assert($resolved !== null);
    expect($resolved->isWildcard())->toBeTrue();
    expect($resolved->to())->toBe(OrderState::Cancelled);
    expect($resolved->guards())->toBe([NotYetShippedGuard::class]);
});

it('blocks a wildcard self-loop unless includeSelf is true', function (): void {
    $inspector = new EnumInspector();

    // OrderState wildcard (-> Cancelled) has includeSelf:false → Cancelled->Cancelled blocked.
    expect($inspector->resolve(OrderState::Cancelled, OrderState::Cancelled))->toBeNull();

    // DocumentState wildcard (-> Archived) has includeSelf:true → Archived->Archived allowed.
    $selfLoop = $inspector->resolve(DocumentState::Archived, DocumentState::Archived);
    assert($selfLoop !== null);
    expect($selfLoop->isWildcard())->toBeTrue();
    expect($selfLoop->to())->toBe(DocumentState::Archived);
});

/*
|--------------------------------------------------------------------------
| Precedence
|--------------------------------------------------------------------------
*/

it('prefers a matching case-level rule over a matching wildcard for the same target', function (): void {
    // Draft declares its own `-> Archived` with NO hooks; the class wildcard `-> Archived`
    // carries a before hook. The case-level rule must win, so before() is empty.
    $resolved = (new EnumInspector())->resolve(DocumentState::Draft, DocumentState::Archived);

    assert($resolved !== null);
    expect($resolved->isWildcard())->toBeFalse();
    expect($resolved->before())->toBe([]); // wildcard's ReserveInventoryHook did NOT win
});

it('uses the first declared case-level rule when two target the same case', function (): void {
    // Review declares two `-> Published` rules: first has a before hook, second an after.
    $resolved = (new EnumInspector())->resolve(DocumentState::Review, DocumentState::Published);

    assert($resolved !== null);
    expect($resolved->isWildcard())->toBeFalse();
    expect($resolved->before())->toBe([ReserveInventoryHook::class]); // first rule
    expect($resolved->after())->toBe([]);                             // second rule ignored
});

/*
|--------------------------------------------------------------------------
| No matching rule
|--------------------------------------------------------------------------
*/

it('returns null when no case-level or wildcard rule matches', function (): void {
    // Shipped has no case-level rules and the only wildcard targets Cancelled.
    expect((new EnumInspector())->resolve(OrderState::Shipped, OrderState::Pending))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

it('returns the declared StateMachineConfig', function (): void {
    $config = (new EnumInspector())->config(OrderState::class);

    expect($config)->toBeInstanceOf(StateMachineConfig::class);
    expect($config->dispatchEvents)->toBeFalse();
    expect($config->event)->toBe(StateTransitioned::class);
});

it('returns a sane default StateMachineConfig when the attribute is absent', function (): void {
    $config = (new EnumInspector())->config(DocumentState::class);

    expect($config->dispatchEvents)->toBeTrue();
    expect($config->event)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Reflection caching (§5.6)
|--------------------------------------------------------------------------
*/

it('caches compiled rules per FQCN so repeated resolution is consistent', function (): void {
    $inspector = new EnumInspector();

    $first = $inspector->resolve(OrderState::Paid, OrderState::Shipped);
    $second = $inspector->resolve(OrderState::Paid, OrderState::Shipped);

    // Each resolve() builds a fresh ResolvedTransition, but from the same cached
    // CompiledEnum — so the compiled metadata is identical across calls.
    assert($first !== null);
    assert($second !== null);
    expect($second->guards())->toBe($first->guards());
    expect($second->before())->toBe($first->before());
    expect($second->after())->toBe($first->after());
    expect($second->isWildcard())->toBe($first->isWildcard());
});

it('separately caches distinct enum FQCNs', function (): void {
    $inspector = new EnumInspector();

    // Resolving one enum must not pollute another's compiled rules.
    expect($inspector->resolve(OrderState::Pending, OrderState::Paid))->not->toBeNull();
    expect($inspector->resolve(DocumentState::Draft, DocumentState::Review))->not->toBeNull();

    // And the OrderState rules still resolve correctly afterward.
    expect($inspector->resolve(OrderState::Paid, OrderState::Shipped))->not->toBeNull();
});

it('clearCache resets the static cache', function (): void {
    $inspector = new EnumInspector();

    // Warm the cache.
    $inspector->config(OrderState::class);

    EnumInspector::clearCache();

    // After a reset the inspector re-reflects and still resolves correctly.
    $config = $inspector->config(OrderState::class);
    expect($config->dispatchEvents)->toBeFalse();
    expect($inspector->resolve(OrderState::Paid, OrderState::Shipped))->not->toBeNull();
});
