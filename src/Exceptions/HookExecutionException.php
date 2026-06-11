<?php

declare(strict_types=1);

namespace EnumStateMachine\Exceptions;

use BackedEnum;
use Throwable;

/**
 * Thrown when an `after` hook fails.
 *
 * By the time an after-hook runs the state change is already committed, so this
 * exception signals "the transition completed but a side-effect failed". The
 * original throwable is preserved via {@see self::getPrevious()}.
 */
final class HookExecutionException extends StateMachineException
{
    /**
     * @param class-string $hook The failing hook's class-string.
     */
    private function __construct(
        string $message,
        private readonly BackedEnum $from,
        private readonly BackedEnum $to,
        private readonly string $hook,
        Throwable $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Wrap a throwable raised by an after-hook.
     *
     * @param class-string $hook The failing hook's class-string.
     */
    public static function fromHook(string $hook, BackedEnum $from, BackedEnum $to, Throwable $previous): self
    {
        return new self(
            sprintf(
                'After-hook "%s" failed during transition from "%s" to "%s": %s',
                $hook,
                self::label($from),
                self::label($to),
                $previous->getMessage(),
            ),
            $from,
            $to,
            $hook,
            $previous,
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
     * The failing hook's class-string.
     *
     * @return class-string
     */
    public function getHook(): string
    {
        return $this->hook;
    }

    /**
     * A human-readable label for an enum case (e.g. "OrderState::Shipped").
     */
    private static function label(BackedEnum $case): string
    {
        return $case::class . '::' . $case->name;
    }
}
