<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;

/**
 * Default constant-name generator: slugifies a literal value into a valid,
 * deterministic UPPER_SNAKE_CASE constant name, with a fallback hint and a
 * last-resort character-code name so it never throws.
 */
final readonly class ConstantNameGenerator implements ConstantNameGeneratorInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function generate(string $rawValue, ?string $fallbackHint = null): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $rawValue) ?? $rawValue;
        $name = strtoupper(trim($slug, '_'));

        if ($name !== '') {
            // Constants can't start with a digit either.
            return ctype_digit($name[0]) ? 'V_' . $name : $name;
        }

        if ($fallbackHint !== null) {
            $hintSlug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $fallbackHint) ?? $fallbackHint);
            return trim($hintSlug, '_') . '_SEPARATOR';
        }

        // Last resort: name it after the value's own character codes so it's
        // at least deterministic and non-empty, rather than throwing.
        return 'CHAR_' . implode('_', array_map('ord', str_split($rawValue)));
    }
}
