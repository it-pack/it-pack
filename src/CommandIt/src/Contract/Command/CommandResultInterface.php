<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\Domain\CommandResultStatusInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * The immutable outcome of a command: a status, an optional payload, and the
 * execution that produced it.
 *
 * @api
 */
interface CommandResultInterface
{
    public function status(): CommandResultStatusInterface;

    /**
     * @return mixed
     */
    public function payload(): mixed;

    public function execution(): ?ExecutionArtifactInterface;

    /**
     * Returns a copy of this result carrying the given execution artifact.
     *
     * The receiver is never mutated.
     */
    public function withExecution(ExecutionArtifactInterface $execution): CommandResultInterface;
}
