<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\CommandResultStatusInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * The immutable outcome of a command.
 *
 * @api
 */
final readonly class CommandResult implements CommandResultInterface
{
    public function __construct(
        private CommandResultStatusInterface $status,
        private mixed $payload = null,
        private ?ExecutionArtifactInterface $execution = null,
    ) {
    }

    public static function succeed(
        mixed $payload = null,
        ?ExecutionArtifactInterface $execution = null,
    ): self {
        return new self(CommandResultStatus::Succeeded, $payload, $execution);
    }

    public static function fail(
        mixed $payload = null,
        ?ExecutionArtifactInterface $execution = null,
    ): self {
        return new self(CommandResultStatus::Failed, $payload, $execution);
    }

    public function status(): CommandResultStatusInterface
    {
        return $this->status;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function execution(): ?ExecutionArtifactInterface
    {
        return $this->execution;
    }

    public function withPayload(mixed $payload): CommandResultInterface
    {
        return new self($this->status, $payload, $this->execution);
    }

    public function withExecution(ExecutionArtifactInterface $execution): CommandResultInterface
    {
        return new self($this->status, $this->payload, $execution);
    }
}
