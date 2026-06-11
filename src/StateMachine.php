<?php

declare(strict_types=1);

namespace EnumStateMachine;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;
use EnumStateMachine\Contracts\StateHookInterface;
use EnumStateMachine\Events\StateTransitioned;
use EnumStateMachine\Exceptions\HookExecutionException;
use EnumStateMachine\Exceptions\InvalidTransitionException;
use EnumStateMachine\Exceptions\StateMachineException;
use EnumStateMachine\Reflection\EnumInspector;
use EnumStateMachine\Reflection\ResolvedTransition;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * The core engine: evaluates transitions, runs guards, fires before/after hooks,
 * and (optionally) dispatches a PSR-14 event.
 *
 * Statefulness: the machine is MUTABLE. A successful {@see self::transitionTo()}
 * updates its own {@see self::$currentState}, so {@see self::getCurrentState()}
 * reflects the new state afterward (§4.2). The caller still persists the returned
 * value to their domain model.
 *
 * Dependencies are both optional and framework-agnostic (§2):
 *   - a PSR-11 container resolves guard/hook classes via DI (§5.5); without one,
 *     classes are built with `new $class()` (they must have a no-arg constructor);
 *   - a PSR-14 dispatcher emits the transition event (§4.6); without one (or with
 *     `dispatchEvents: false`) no event is emitted.
 *
 * Guard/hook classes are resolved LAZILY at transition time (never in the
 * constructor, never in {@see EnumInspector}); only the matched rule's classes
 * are materialized.
 *
 * Static analysis (§5.7): the class is generic over the concrete enum type via
 * `@template T of \BackedEnum`. The native runtime signatures stay the broad
 * `\BackedEnum`; the PHPDoc generics narrow `getCurrentState()` / `transitionTo()`
 * back to the caller's specific enum (e.g. `OrderState`) for PHPStan/Psalm. The
 * narrowing is annotation-only and has no runtime effect.
 *
 * @template T of \BackedEnum
 */
final class StateMachine
{
    private readonly EnumInspector $inspector;

    /**
     * @var T
     */
    private BackedEnum $currentState;

    /**
     * @param T                             $currentState The machine's starting state (e.g. from a DB model).
     * @param ContainerInterface|null       $container    Optional PSR-11 container for resolving guards/hooks.
     * @param EventDispatcherInterface|null $dispatcher   Optional PSR-14 dispatcher for transition events.
     */
    public function __construct(
        BackedEnum $currentState,
        private readonly ?ContainerInterface $container = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->currentState = $currentState;
        $this->inspector = new EnumInspector();
    }

    /**
     * The machine's current state. Reflects the target after a successful
     * {@see self::transitionTo()}.
     *
     * @return T
     */
    public function getCurrentState(): BackedEnum
    {
        return $this->currentState;
    }

    /**
     * Whether a transition to `$targetState` is currently permitted.
     *
     * Performs steps 1–2 of §5.2 only: resolve the rule, then run its guards.
     * Returns `false` (rather than throwing) when no rule matches or any guard
     * vetoes; `true` when a rule matches and every guard passes. Before/after
     * hooks are NOT run here and no state change occurs.
     *
     * Because guards are invoked here, they must be side-effect-free (§4.3): a
     * `can()` probe (e.g. to toggle a UI button) must not mutate anything.
     *
     * A guard that THROWS propagates raw (it is not swallowed into `false`),
     * mirroring {@see self::transitionTo()} so a domain-specific veto reason
     * surfaces consistently in both paths.
     *
     * @param T $targetState
     */
    public function can(BackedEnum $targetState, mixed $context = null): bool
    {
        $rule = $this->inspector->resolve($this->currentState, $targetState);

        if ($rule === null) {
            return false;
        }

        foreach ($rule->guards() as $guardClass) {
            $guard = $this->resolveGuard($guardClass);

            if ($guard($this->currentState, $targetState, $context) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Perform a transition to `$targetState`, following the §5.2 order exactly:
     *
     *   1. Resolve the rule. No rule → {@see InvalidTransitionException::noRule()}.
     *   2. Run guards in declared order. First `false` →
     *      {@see InvalidTransitionException::rejectedByGuard()}. A guard that
     *      THROWS propagates raw. State unchanged.
     *   3. Run `before` hooks in declared order. A throw propagates raw and
     *      aborts; state unchanged.
     *   4. Mutate currentState := target.
     *   5. Run `after` hooks in declared order. A throw skips the remaining
     *      after-hooks and raises {@see HookExecutionException} (state stays
     *      changed; no event is dispatched).
     *   6. Dispatch the transition event — only if a dispatcher was supplied AND
     *      config.dispatchEvents is true (§4.6).
     *   7. Return the new state.
     *
     * @param T $targetState
     *
     * @return T The new (target) state, identical to `$targetState`.
     *
     * @throws InvalidTransitionException No matching rule, or a guard vetoed.
     * @throws HookExecutionException     An after-hook failed (state already changed).
     * @throws StateMachineException      A resolved guard/hook does not implement its interface.
     * @throws Throwable                  Propagated raw from a guard or a before-hook.
     */
    public function transitionTo(BackedEnum $targetState, mixed $context = null): BackedEnum
    {
        $from = $this->currentState;

        // 1. Resolve the rule.
        $rule = $this->inspector->resolve($from, $targetState);

        if ($rule === null) {
            throw InvalidTransitionException::noRule($from, $targetState);
        }

        // 2. Guards — first false vetoes; a throw propagates raw. State unchanged.
        foreach ($rule->guards() as $guardClass) {
            $guard = $this->resolveGuard($guardClass);

            if ($guard($from, $targetState, $context) === false) {
                throw InvalidTransitionException::rejectedByGuard($from, $targetState, $guardClass);
            }
        }

        // 3. Before hooks — a throw propagates raw and aborts. State unchanged.
        foreach ($rule->before() as $hookClass) {
            $hook = $this->resolveHook($hookClass);
            $hook($from, $targetState, $context);
        }

        // 4. Commit the state change.
        $this->currentState = $targetState;

        // 5. After hooks — a throw skips the rest and is wrapped. State stays changed.
        foreach ($rule->after() as $hookClass) {
            $hook = $this->resolveHook($hookClass);

            try {
                $hook($from, $targetState, $context);
            } catch (Throwable $e) {
                throw HookExecutionException::fromHook($hookClass, $from, $targetState, $e);
            }
        }

        // 6. Dispatch the event (only when enabled and a dispatcher is present).
        $this->dispatch($rule, $from, $targetState, $context);

        // 7. Return the new state.
        return $this->currentState;
    }

    /**
     * Dispatch the transition event when a dispatcher is present AND
     * config.dispatchEvents is true. The event class is config.event when set,
     * else {@see StateTransitioned}, constructed as `($from, $to, $context)`.
     */
    private function dispatch(ResolvedTransition $rule, BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        $config = $this->inspector->config($from::class);

        if ($config->dispatchEvents === false) {
            return;
        }

        $eventClass = $config->event ?? StateTransitioned::class;

        $this->dispatcher->dispatch(new $eventClass($from, $to, $context));
    }

    /**
     * Resolve a guard class-string to an instance and assert it is a guard.
     *
     * @param class-string<GuardInterface> $class
     *
     * @throws StateMachineException When the resolved object is not a {@see GuardInterface}.
     */
    private function resolveGuard(string $class): GuardInterface
    {
        $instance = $this->make($class);

        if (! $instance instanceof GuardInterface) {
            throw new StateMachineException(sprintf(
                'Guard "%s" must implement %s, got %s.',
                $class,
                GuardInterface::class,
                get_debug_type($instance),
            ));
        }

        return $instance;
    }

    /**
     * Resolve a hook class-string to an instance and assert it is a hook.
     *
     * @param class-string<StateHookInterface> $class
     *
     * @throws StateMachineException When the resolved object is not a {@see StateHookInterface}.
     */
    private function resolveHook(string $class): StateHookInterface
    {
        $instance = $this->make($class);

        if (! $instance instanceof StateHookInterface) {
            throw new StateMachineException(sprintf(
                'Hook "%s" must implement %s, got %s.',
                $class,
                StateHookInterface::class,
                get_debug_type($instance),
            ));
        }

        return $instance;
    }

    /**
     * Materialize a class-string: via the PSR-11 container when one was supplied
     * (enabling auto-wiring), otherwise `new $class()` (§5.5). Interface
     * conformance is enforced by the callers ({@see self::resolveGuard()} /
     * {@see self::resolveHook()}), so this returns an untyped object.
     *
     * @param class-string $class
     */
    private function make(string $class): object
    {
        if ($this->container !== null) {
            $instance = $this->container->get($class);

            if (! is_object($instance)) {
                throw new StateMachineException(sprintf(
                    'Container returned a non-object for "%s" (%s).',
                    $class,
                    get_debug_type($instance),
                ));
            }

            return $instance;
        }

        return new $class();
    }
}
