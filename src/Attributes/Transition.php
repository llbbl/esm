<?php

declare(strict_types=1);

namespace EnumStateMachine\Attributes;

use Attribute;
use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Declares an allowed transition, optionally gated by guards and decorated with
 * before/after hooks.
 *
 * Placed on an enum case it defines transitions from that case; placed on the
 * enum class it defines a wildcard transition allowed from any state.
 *
 * This is a pure value-holder: it carries metadata only and performs no logic.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param BackedEnum|array<int, BackedEnum>                                       $to          A single target case or a list of cases this rule permits.
     * @param class-string<GuardInterface>|array<int, class-string<GuardInterface>>|null $guard     Guard class-string(s); all must return true.
     * @param class-string<StateHookInterface>|array<int, class-string<StateHookInterface>>|null $before Hook class-string(s) run before the state change.
     * @param class-string<StateHookInterface>|array<int, class-string<StateHookInterface>>|null $after  Hook class-string(s) run after the state change.
     * @param bool                                                                    $includeSelf Wildcard only: also allow target -> target self-loops.
     */
    public function __construct(
        public readonly BackedEnum|array $to,
        public readonly string|array|null $guard = null,
        public readonly string|array|null $before = null,
        public readonly string|array|null $after = null,
        public readonly bool $includeSelf = false,
    ) {
    }
}
