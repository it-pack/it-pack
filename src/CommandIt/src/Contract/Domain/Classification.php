<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

use ItPack\CommandIt\Behavior\Domain\LabelableBehavior;

/**
 * The classification of a context entry, controlling export and propagation.
 *
 * @api
 */
enum Classification: string implements ClassificationInterface
{
    use LabelableBehavior;

    case Public = 'public';
    case Internal = 'internal';
    case Secret = 'secret';
    case Debug = 'debug';

    public function isSecret(): bool
    {
        return $this === self::Secret;
    }
}
