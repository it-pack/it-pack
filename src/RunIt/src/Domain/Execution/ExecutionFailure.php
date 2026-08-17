<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;

/**
 * Failed execution result metadata.
 *
 * A failure carries the terminal failure status (`failed`, `cancelled`, or
 * `timed_out`) and a safe, throwable-free description of what ended the execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionFailure implements ExecutionResultInterface
{
    public function __construct(
        private readonly ExecutionStatusInterface $status,
        private readonly ThrowableDescriptionInterface $description,
    ) {
    }

    public static function of(ExecutionStatusInterface $status, ThrowableDescriptionInterface $description): ExecutionResultInterface
    {
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
