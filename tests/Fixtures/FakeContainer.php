<?php

declare(strict_types=1);

namespace EnumStateMachine\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Minimal PSR-11 container backed by a class-string → instance map, recording
 * every `get()` so tests can prove the engine resolves guards/hooks through the
 * container (rather than `new $class()`) when one is supplied.
 */
final class FakeContainer implements ContainerInterface
{
    /**
     * @param array<class-string, object> $bindings
     */
    public function __construct(
        private array $bindings = [],
        /** @var list<string> */
        public array $requested = [],
    ) {
    }

    /**
     * @param class-string $id
     */
    public function set(string $id, object $instance): void
    {
        $this->bindings[$id] = $instance;
    }

    public function get(string $id): object
    {
        $this->requested[] = $id;

        if (! isset($this->bindings[$id])) {
            throw new class ('No binding for ' . $id) extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->bindings[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }
}
