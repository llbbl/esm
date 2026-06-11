<?php

declare(strict_types=1);

namespace EnumStateMachine\Events;

use BackedEnum;

/**
 * PSR-14 event emitted after a transition fully succeeds (state changed and all
 * after-hooks ran). Observers may log or react, but cannot cancel the transition.
 */
final class StateTransitioned
{
    public function __construct(
        public readonly BackedEnum $from,
        public readonly BackedEnum $to,
        public readonly mixed $context,
    ) {
    }
}
