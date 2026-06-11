<?php

declare(strict_types=1);

namespace EnumStateMachine\Reflection;

use BackedEnum;
use EnumStateMachine\Attributes\StateMachineConfig;
use EnumStateMachine\Attributes\Transition;

/**
 * Immutable, cached compilation of one enum type's transition metadata.
 *
 * Built once per enum FQCN by {@see EnumInspector} and held in its process-static
 * cache (§5.6). Holds only attribute metadata — the materialized
 * `#[Transition]` / `#[StateMachineConfig]` value objects — never instantiated
 * guards/hooks.
 *
 * @internal
 */
final class CompiledEnum
{
    /**
     * @param list<Transition>                $wildcards Class-level (wildcard) rules, in declared order.
     * @param array<string, list<Transition>> $caseRules Case-level rules keyed by case name, in declared order.
     */
    public function __construct(
        public readonly StateMachineConfig $config,
        public readonly array $wildcards,
        public readonly array $caseRules,
    ) {
    }

    /**
     * Case-level rules declared on the given case, in declared order.
     *
     * @return list<Transition>
     */
    public function caseRules(BackedEnum $case): array
    {
        return $this->caseRules[$case->name] ?? [];
    }

    /**
     * Class-level wildcard rules, in declared order.
     *
     * @return list<Transition>
     */
    public function wildcardRules(): array
    {
        return $this->wildcards;
    }
}
