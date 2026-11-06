<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;

/**
 * Successful execution result metadata.
 *
 * A success carries the terminal `succeeded` status and no throwable description.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionSuccess implements ExecutionResultInterface
{
    private function __construct(private ExecutionStatusInterface $status)
    {
    }

    public static function create(): ExecutionResultInterface
    {
        return new self(ExecutionStatus::Succeeded);
    }

    public function status(): ExecutionStatusInterface
    {
        return $this->status;
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return null;
    }
}
