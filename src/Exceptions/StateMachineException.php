<?php

declare(strict_types=1);

namespace EnumStateMachine\Exceptions;

use RuntimeException;

/**
 * Base exception for all state-machine errors.
 *
 * Catch this to handle any failure originating from the engine.
 */
class StateMachineException extends RuntimeException
{
}
