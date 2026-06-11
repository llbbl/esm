<?php

declare(strict_types=1);

namespace EnumStateMachine\Attributes;

use Attribute;

/**
 * Optional class-level attribute for machine-wide configuration.
 *
 * This is a pure value-holder: it carries metadata only and performs no logic.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class StateMachineConfig
{
    /**
     * @param bool        $dispatchEvents When false, no PSR-14 events are emitted even if a dispatcher is supplied.
     * @param class-string|null $event    FQCN of a custom event class; defaults to StateTransitioned when null.
     */
    public function __construct(
        public readonly bool $dispatchEvents = true,
        public readonly ?string $event = null,
    ) {
    }
}
