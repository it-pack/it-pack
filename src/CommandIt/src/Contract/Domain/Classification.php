<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * The classification of a context entry, controlling export and propagation.
 *
 * @api
 */
enum Classification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Secret = 'secret';
    case Debug = 'debug';

    public function isSecret(): bool
    {
        return $this === self::Secret;
    }

    public function label(): string
    {
        return $this->value;
    }
}
