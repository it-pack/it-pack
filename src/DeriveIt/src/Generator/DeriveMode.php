<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

/**
 * Supported derivation modes (F4 / R.4). Replaces the bare mode string literals
 * that previously flowed through {@see \ItPack\DeriveIt\Cli\DeriveCliInput} and the
 * CLI parser, so the allowed set lives in exactly one typed place and adding a mode
 * is a single enum-case addition (OCP, see {@see \ItPack\DeriveIt\Cli\DeriveCommand}).
 */
enum DeriveMode: string
{
    case FLAT = 'flat';
    case PER_TRAIT = 'per-trait';
    case ADAPTER = 'adapter';
    case FAMILY = 'family';
    case FAMILY_ADAPTER = 'family-adapter';
}
