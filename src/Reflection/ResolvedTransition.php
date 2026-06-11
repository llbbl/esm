<?php

declare(strict_types=1);

namespace EnumStateMachine\Reflection;

use BackedEnum;
use EnumStateMachine\Contracts\GuardInterface;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Immutable value object describing the single transition rule that
 * {@see EnumInspector::resolve()} matched for a `$from -> $to` request.
 *
 * It holds only metadata — normalized class-string lists and the resolved
 * target — never instantiated guards/hooks. Materializing those classes (and
 * any container wiring) is the StateMachine engine's responsibility (Unit 3).
 *
 * The constructor normalizes the attribute's loose `string|array|null` guard and
 * hook shapes into clean, zero-indexed `list<class-string>` values:
 *   - `null`          -> `[]`
 *   - a single string -> `[$string]`
 *   - an array        -> a re-indexed list
 *
 * @phpstan-type GuardList list<class-string<GuardInterface>>
 * @phpstan-type HookList  list<class-string<StateHookInterface>>
 */
final class ResolvedTransition
{
    /** @var GuardList */
    private readonly array $guards;

    /** @var HookList */
    private readonly array $before;

    /** @var HookList */
    private readonly array $after;

    /**
     * @param class-string<GuardInterface>|array<int, class-string<GuardInterface>>|null            $guard  Raw guard class-string(s) from the attribute.
     * @param class-string<StateHookInterface>|array<int, class-string<StateHookInterface>>|null     $before Raw before-hook class-string(s) from the attribute.
     * @param class-string<StateHookInterface>|array<int, class-string<StateHookInterface>>|null      $after  Raw after-hook class-string(s) from the attribute.
     */
    public function __construct(
        private readonly BackedEnum $to,
        string|array|null $guard,
        string|array|null $before,
        string|array|null $after,
        private readonly bool $isWildcard,
    ) {
        $this->guards = self::normalize($guard);
        $this->before = self::normalize($before);
        $this->after = self::normalize($after);
    }

    /**
     * The resolved transition target.
     */
    public function to(): BackedEnum
    {
        return $this->to;
    }

    /**
     * Whether the matched rule came from a class-level (wildcard) attribute
     * rather than a case-level one.
     */
    public function isWildcard(): bool
    {
        return $this->isWildcard;
    }

    /**
     * Guard class-strings, in declared order. Empty when the rule has no guards.
     *
     * @return GuardList
     */
    public function guards(): array
    {
        return $this->guards;
    }

    /**
     * Before-hook class-strings, in declared order. Empty when none are declared.
     *
     * @return HookList
     */
    public function before(): array
    {
        return $this->before;
    }

    /**
     * After-hook class-strings, in declared order. Empty when none are declared.
     *
     * @return HookList
     */
    public function after(): array
    {
        return $this->after;
    }

    /**
     * Normalize a `string|array|null` attribute value into a zero-indexed list.
     *
     * @template T of string
     *
     * @param T|array<int, T>|null $value
     *
     * @return list<T>
     */
    private static function normalize(string|array|null $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return [$value];
        }

        return array_values($value);
    }
}
