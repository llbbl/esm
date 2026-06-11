<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

/**
 * A class that implements neither GuardInterface nor StateHookInterface, used to
 * prove the engine rejects a resolved object that does not conform (§5.5).
 */
final class NotAGuard
{
}
