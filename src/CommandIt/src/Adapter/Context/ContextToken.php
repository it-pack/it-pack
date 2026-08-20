<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Context;

use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque token returned when a context is entered.
 *
 * @api
 */
final class ContextToken implements ContextTokenInterface
{
    public function __construct(
        public readonly CommandContextInterface $context,
    ) {
    }
}
