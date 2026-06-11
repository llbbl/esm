<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Minimal PSR-14 dispatcher that records every dispatched event so tests can
 * assert what (if anything) was emitted, and returns the event unchanged.
 */
final class FakeDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
