<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Generator\DeriveConfig;

/**
 * CLI `--preset` vocabulary (F7 / R3-8). Replaces the bare `'strict'` /
 * `'adapter'` / `'family'` literals that previously flowed through
 * {@see ArgParser}'s match arms, error message, and help text, so the allowed
 * set lives in exactly one typed place — mirroring what {@see DeriveMode} did
 * for modes. Adding a preset is a single enum case plus its config mapping.
 */
enum Preset: string
{
    case STRICT = 'strict';
    case ADAPTER = 'adapter';
    case FAMILY = 'family';

    /**
     * @return list<string> valid `--preset=` values, for messages and help text.
     */
    public static function values(): array
    {
        return array_map(static fn (self $preset): string => $preset->value, self::cases());
    }

    /**
     * The {@see DeriveConfig} preset this value selects.
     */
    public function toConfig(): DeriveConfig
    {
        return match ($this) {
            self::STRICT => DeriveConfig::strict(),
            self::ADAPTER => DeriveConfig::forAdapter(),
            self::FAMILY => DeriveConfig::forFamily(),
        };
    }
}
