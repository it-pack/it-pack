<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

/**
 * Default NameSlugifierInterface implementation. Stateless and immutable;
 * behavior is byte-compatible with the former private copies inside
 * BackedEnumTarget and FlagSetTarget, except that the empty-result fallback
 * is a parameter (they used hard-coded 'Value' / 'Flag').
 */
final readonly class NameSlugifier implements NameSlugifierInterface
{
    public function pascalCase(string $value, string $fallback = 'Value'): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? $value;
        $parts = array_filter(explode(' ', trim($clean)), static fn (string $p): bool => $p !== '');
        $name = implode('', array_map(ucfirst(...), $parts));

        if ($name === '') {
            $name = $fallback;
        }

        if (ctype_digit($name[0])) {
            $name = 'V' . $name;
        }

        return $name;
    }
}
