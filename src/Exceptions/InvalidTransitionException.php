<?php

declare(strict_types=1);

namespace EnumStateMachine\Exceptions;

use BackedEnum;

/**
 * Thrown when a transition is not permitted: either no rule matches the
 * requested target, or a declared guard vetoed the transition.
 */
final class InvalidTransitionException extends StateMachineException
{
    /**
     * @param class-string|null $guard The rejecting guard's class-string, when a guard caused the rejection.
     */
    private function __construct(
        string $message,
        private readonly BackedEnum $from,
        private readonly BackedEnum $to,
        private readonly ?string $guard = null,
    ) {
        parent::__construct($message);
    }

    /**
     * No transition rule matched the requested target.
     */
    public static function noRule(BackedEnum $from, BackedEnum $to): self
    {
        return new self(
            sprintf(
                'No transition rule from "%s" to "%s".',
                self::label($from),
                self::label($to),
            ),
            $from,
            $to,
        );
    }

    /**
     * A guard returned false, vetoing the transition.
     *
     * @param class-string $guard The rejecting guard's class-string.
     */
    public static function rejectedByGuard(BackedEnum $from, BackedEnum $to, string $guard): self
    {
        return new self(
            sprintf(
                'Transition from "%s" to "%s" was rejected by guard "%s".',
                self::label($from),
                self::label($to),
                $guard,
            ),
            $from,
            $to,
            $guard,
        );
    }

    public function getFrom(): BackedEnum
    {
        return $this->from;
    }

    public function getTo(): BackedEnum
    {
        return $this->to;
    }

    /**
     * The rejecting guard's class-string, or null when the failure was a missing rule.
     *
     * @return class-string|null
     */
    public function getGuard(): ?string
    {
        return $this->guard;
    }

    /**
     * A human-readable label for an enum case (e.g. "OrderState::Shipped").
     */
    private static function label(BackedEnum $case): string
    {
        return $case::class . '::' . $case->name;
    }
}
