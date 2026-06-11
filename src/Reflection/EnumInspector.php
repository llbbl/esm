<?php

declare(strict_types=1);

namespace EnumStateMachine\Reflection;

use BackedEnum;
use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;
use ReflectionEnum;

/**
 * Reflection layer: parses an enum's `#[Transition]` and `#[StateMachineConfig]`
 * attributes into compiled rules and resolves a `$from -> $to` request to a
 * single {@see ResolvedTransition} (or `null` when no rule allows it).
 *
 * Design — cache shape & lifetime:
 *   EnumInspector is instantiated (`new EnumInspector()`) but its cache is a
 *   PROCESS-STATIC map shared across every instance, keyed by enum FQCN:
 *
 *       static array<class-string<BackedEnum>, CompiledEnum> $cache
 *
 *   Each enum type is reflected exactly once per process (§5.6); the entry holds
 *   only metadata (targets, guard/hook class-strings, flags, config) — never
 *   instantiated guards/hooks. Re-resolving the same enum reuses the cached
 *   {@see CompiledEnum}. {@see self::clearCache()} empties the map for test
 *   isolation.
 *
 * This unit is pure parsing/resolution: it does not instantiate guards/hooks or
 * touch a container — that is the StateMachine engine's job (Unit 3).
 */
final class EnumInspector
{
    /**
     * Process-static compiled-rule cache, keyed by enum FQCN.
     *
     * @var array<class-string<BackedEnum>, CompiledEnum>
     */
    private static array $cache = [];

    /**
     * Resolve the single rule allowing `$from -> $to`, applying the §5.1
     * precedence algorithm, or `null` when no rule matches.
     *
     * Precedence: a matching case-level rule beats a matching wildcard; among
     * rules of the same kind the first declared wins. A wildcard never permits a
     * self-loop (`$from === $to`) unless its `includeSelf` flag is set.
     */
    public function resolve(BackedEnum $from, BackedEnum $to): ?ResolvedTransition
    {
        $compiled = $this->compile($from::class);

        // 1. Case-level rules for $from, in declared order — first match wins.
        foreach ($compiled->caseRules($from) as $rule) {
            if ($this->matches($rule, $to)) {
                return new ResolvedTransition(
                    to: $to,
                    guard: $rule->guard,
                    before: $rule->before,
                    after: $rule->after,
                    isWildcard: false,
                );
            }
        }

        // 2. Wildcard (class-level) rules, in declared order — first match wins.
        $isSelfLoop = $from === $to;

        foreach ($compiled->wildcardRules() as $rule) {
            if ($isSelfLoop && $rule->includeSelf === false) {
                continue;
            }

            if ($this->matches($rule, $to)) {
                return new ResolvedTransition(
                    to: $to,
                    guard: $rule->guard,
                    before: $rule->before,
                    after: $rule->after,
                    isWildcard: true,
                );
            }
        }

        return null;
    }

    /**
     * The class-level `#[StateMachineConfig]`, or a default instance
     * (`dispatchEvents: true, event: null`) when the attribute is absent (§4.5).
     *
     * @param class-string<BackedEnum> $enumClass
     */
    public function config(string $enumClass): StateMachineConfig
    {
        return $this->compile($enumClass)->config;
    }

    /**
     * Empty the process-static cache. Intended for test isolation so cache state
     * does not leak across cases.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Whether a rule's normalized `to` list contains the requested target.
     */
    private function matches(Transition $rule, BackedEnum $to): bool
    {
        $targets = is_array($rule->to) ? $rule->to : [$rule->to];

        foreach ($targets as $target) {
            if ($target === $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compile (and cache) an enum type's attributes. Reflection runs once per
     * FQCN per process; subsequent calls return the cached {@see CompiledEnum}.
     *
     * @param class-string<BackedEnum> $enumClass
     */
    private function compile(string $enumClass): CompiledEnum
    {
        if (isset(self::$cache[$enumClass])) {
            return self::$cache[$enumClass];
        }

        $reflection = new ReflectionEnum($enumClass);

        // Class-level wildcard rules, in declared order.
        $wildcards = [];
        foreach ($reflection->getAttributes(Transition::class) as $attribute) {
            $wildcards[] = $attribute->newInstance();
        }

        // Class-level config (or default when absent).
        $configAttributes = $reflection->getAttributes(StateMachineConfig::class);
        $config = $configAttributes === []
            ? new StateMachineConfig()
            : $configAttributes[0]->newInstance();

        // Case-level rules, keyed by case name, in declared order.
        $caseRules = [];
        foreach ($reflection->getCases() as $case) {
            $rules = [];
            foreach ($case->getAttributes(Transition::class) as $attribute) {
                $rules[] = $attribute->newInstance();
            }
            $caseRules[$case->getName()] = $rules;
        }

        return self::$cache[$enumClass] = new CompiledEnum($config, $wildcards, $caseRules);
    }
}
