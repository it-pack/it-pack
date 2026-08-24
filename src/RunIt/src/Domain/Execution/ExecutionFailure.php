<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;

/**
 * Failed execution result metadata.
 *
 * A failure carries the terminal failure status (`failed`, `cancelled`, or
 * `timed_out`) and a safe, throwable-free description of what ended the execution.
 * Construction is symmetric with {@see ExecutionSuccess}: the constructor is
 * private and {@see self::of()} validates that the status is terminal, so a
 * failure can never be created with a non-terminal status.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionFailure implements ExecutionResultInterface
{
    private function __construct(
        private readonly ExecutionStatusInterface $status,
        private readonly ThrowableDescriptionInterface $description,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the status is not terminal
     */
    public static function of(ExecutionStatusInterface $status, ThrowableDescriptionInterface $description): ExecutionResultInterface
    {
        if (!$status->isTerminal()) {
            throw InvalidLifecycleTransitionException::forNonTerminalFailure($status);
        }

        return new self($status, $description);
    }

    public function status(): ExecutionStatusInterface
    {
        return $this->status;
    }

    public function throwableDescription(): ThrowableDescriptionInterface
    {
        return $this->description;
    }
}
