<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\NamingContextInterface;

/**
 * Derives a UPPER_SNAKE_CASE constant name for a numeric literal from its
 * NamingContext, following the priority in docs/MAGIC_NUMBERS_ROADMAP.md §4:
 *
 *   unit suffix (e.g. `$timeoutMs`) → `TIMEOUT_MS`
 *   comment     (e.g. `// seconds` above `$t = 30`) → `T_SECONDS`
 *   variable/array-key name → `TIMEOUT`
 *   else → null (not promoted)
 *
 * Never invents a name; returns null when no hint is present.
 */
final readonly class NumericNameGenerator implements NumericNameGeneratorInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function generate(LiteralInterface $lit, ?NamingContextInterface $ctx): ?string
    {
        if ($ctx === null) {
            return null;
        }

        $unit = $ctx->unit;
        $variableName = $ctx->variableName;
        $comment = $ctx->comment;

        if ($unit !== null && $variableName !== null) {
            $base = $this->stripUnitSuffix($variableName, $unit);

            return $this->toConstantName($base) . '_' . $this->toConstantName($unit);
        }

        if ($comment !== null) {
            $commentPart = $this->toConstantName($comment);

            return $variableName !== null
                ? $this->toConstantName($variableName) . '_' . $commentPart
                : $commentPart;
        }

        if ($variableName !== null) {
            return $this->toConstantName($variableName);
        }

        return null;
    }

    private function stripUnitSuffix(string $name, string $unit): string
    {
        $stripped = preg_replace('/' . preg_quote($unit, '/') . '$/i', '', $name);

        return $stripped === '' || $stripped === null ? $name : $stripped;
    }

    private function toConstantName(string $value): string
    {
        $split = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $split ?? $value);

        return trim(strtoupper($slug ?? ''), '_');
    }
}
