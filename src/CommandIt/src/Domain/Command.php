<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * An immutable command: a validated intent carrying resolved input and an
 * optional payload.
 *
 * @api
 */
final readonly class Command implements CommandInterface
{
    public function __construct(
        private CommandNameInterface $name,
        private CommandInputInterface $input,
        private mixed $payload = null,
    ) {
    }

    public function name(): CommandNameInterface
    {
        return $this->name;
    }

    public function input(): CommandInputInterface
    {
        return $this->input;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }
}
