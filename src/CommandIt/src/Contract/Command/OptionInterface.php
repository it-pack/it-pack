<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\Domain\InputKindInterface;
use ItPack\CommandIt\Contract\ValueObject\OptionNameInterface;

/**
 * Metadata describing one command option.
 *
 * @api
 */
interface OptionInterface
{
    public function name(): OptionNameInterface;

    public function kind(): InputKindInterface;

    public function required(): bool;

    /**
     * @return mixed
     */
    public function defaultValue(): mixed;

    public function description(): string;

    /**
     * The expected PHP type name, used for coercion (e.g. `string`, `int`, `bool`).
     */
    public function type(): string;
}
