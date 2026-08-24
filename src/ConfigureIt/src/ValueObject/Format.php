<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * Supported configuration source/resource formats.
 *
 * Formats are an implementation detail of adapters; the Core only ever reasons
 * about this enumeration, never about file extensions or vendor types.
 *
 * @see docs/architecture/MODEL.md "Supported Formats"
 */
enum Format: string
{
    case PHP = 'php';
    case JSON = 'json';
    case YAML = 'yaml';
    case ENV = 'env';
    case DOTENV = 'dotenv';
    case NEON = 'neon';
    case TOML = 'toml';
    case INI = 'ini';
    case XML = 'xml';
    case FRONTMATTER = 'frontmatter';
    case UNKNOWN = 'unknown';

    /**
     * @throws \InvalidArgumentException when the format string is unknown.
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unknown configuration format "%s".', $value));
    }
}
