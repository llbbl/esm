<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use BackedEnum;
use EnumStateMachine\Contracts\StateHookInterface;

/**
 * After-hook that captures the `$context` it received (its identity and the
 * `stamp` it carried at after-hook time), so a test can assert it observed the
 * before-hook's mutation on the very same instance.
 */
final class CapturingAfterHook implements StateHookInterface
{
    /** @var list<array{context: mixed, stamp: ?string}> */
    public array $calls = [];

    public function __invoke(BackedEnum $from, BackedEnum $to, mixed $context): void
    {
        $this->calls[] = [
            'context' => $context,
            'stamp' => $context instanceof ContextBox ? $context->stamp : null,
        ];
    }
}
