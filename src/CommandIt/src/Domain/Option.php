<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Behavior\Domain\InputSpecBehavior;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\Domain\InputKindInterface;
use ItPack\CommandIt\Contract\ValueObject\OptionNameInterface;

/**
 * Immutable metadata for one command option.
 *
 * @api
 */
final readonly class Option implements OptionInterface
{
    use InputSpecBehavior;

    public function __construct(
        private OptionNameInterface $name,
        private bool $required = false,
        private mixed $defaultValue = null,
        private string $description = '',
        private string $type = 'string',
    ) {
    }

    public function name(): OptionNameInterface
    {
        return $this->name;
    }

    public function kind(): InputKindInterface
    {
        return InputKind::Option;
    }
}
