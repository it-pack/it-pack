<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Domain\InputKindInterface;
use ItPack\CommandIt\Contract\ValueObject\ArgumentNameInterface;

/**
 * Immutable metadata for one command argument.
 *
 * @api
 */
final readonly class Argument implements ArgumentInterface
{
    public function __construct(
        private ArgumentNameInterface $name,
        private bool $required = false,
        private mixed $defaultValue = null,
        private string $description = '',
        private string $type = 'string',
    ) {
    }

    public function name(): ArgumentNameInterface
    {
        return $this->name;
    }

    public function kind(): InputKindInterface
    {
        return InputKind::Argument;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function defaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function type(): string
    {
        return $this->type;
    }
}
