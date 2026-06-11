<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

/**
 * Shared, process-wide call log so guard/hook spies can record a single ordered
 * sequence of invocations across distinct instances. Tests reset it explicitly.
 *
 * Each entry is a short label such as "guard:Allow" or "after:RecordA".
 */
final class RecordingSpy
{
    /** @var list<string> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function record(string $label): void
    {
        self::$calls[] = $label;
    }
}
