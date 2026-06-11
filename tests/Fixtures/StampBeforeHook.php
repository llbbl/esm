<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * Before-hook that MUTATES the context: stamps a {@see ContextBox}. Lets a test
 * prove the mutation is visible to later (after) hooks and to the dispatched
 * event, confirming the engine threads one shared `$context` through the whole
 * transition rather than copying it.
 */
final class StampBeforeHook implements StateHookInterface
{
    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        if ($context instanceof ContextBox) {
            $context->stamp = 'stamped-by-before';
        }
    }
}
