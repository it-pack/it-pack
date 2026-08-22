<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

/**
 * Derivation visibility surface (F4 / R.4). Replaces the bare `'public'` /
 * `'protected'` string literals that previously flowed through {@see DeriveConfig}
 * and the CLI, so the allowed set lives in exactly one typed place.
 */
enum Visibility: string
{
    case PUBLIC = 'public';
    case PROTECTED = 'protected';
}
