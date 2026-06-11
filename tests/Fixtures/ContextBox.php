<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

/**
 * Mutable context object used to prove that the SAME `$context` instance flows
 * through guards, before-hooks, after-hooks and the dispatched event — so a
 * mutation written by a before-hook is observable everywhere downstream.
 */
final class ContextBox
{
    public function __construct(
        public ?string $stamp = null,
    ) {
    }
}
