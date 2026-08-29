<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * The classification of a context entry, controlling export and propagation.
 *
 * @api
 */
interface ClassificationInterface
{
    /**
     * Returns the stable, lower-case classification name.
     */
    public function label(): string;
}
