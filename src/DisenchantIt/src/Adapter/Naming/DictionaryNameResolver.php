<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Naming;

use ItPack\DisenchantIt\Contract\Naming\DictionaryNameResolverInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Dictionary-backed name resolver. Holds a literal-value → constant-name map and
 * returns the explicit name for a literal when present, or null to let the
 * caller fall back to generated naming.
 *
 * The map can be supplied inline ({@see create}) or loaded from a JSON file of
 * the form `{"literal value": "CONSTANT_NAME", ...}` ({@see fromFile}). Reading
 * a file is the only side effect and is acceptable for an adapter.
 */
final readonly class DictionaryNameResolver implements DictionaryNameResolverInterface
{
    /**
     * @param array<string, string> $map literal value => explicit constant name
     */
    public function __construct(
        private array $map,
    ) {
    }

    /**
     * @param array<string, string> $map
     */
    public static function create(array $map): self
    {
        return new self($map);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Naming dictionary file not found: {$path}");
        }

        $decoded = json_decode((string) /** @phpstan-ignore-next-line */ file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Naming dictionary must be a JSON object: {$path}");
        }

        /** @var array<string, string> $map */
        $map = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $map[$key] = $value;
            }
        }

        return new self($map);
    }

    public function resolve(LiteralInterface $lit): ?string
    {
        return $this->map[$lit->value] ?? null;
    }
}
