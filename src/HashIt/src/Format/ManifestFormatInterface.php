<?php

declare(strict_types=1);

namespace ItPack\HashIt\Format;

use ItPack\HashIt\HashManifest;

/**
 * Text (de)serialization of a {@see HashManifest}. Intentionally separated from
 * the hashing core (roadmap guardrail #7): implementations may import only the
 * core value objects ({@see \ItPack\HashIt\HashManifest}, {@see \ItPack\HashIt\Hash},
 * {@see \ItPack\HashIt\HashAlgorithm}) — never hashers, contexts or factories.
 */
interface ManifestFormatInterface
{
    public function serialize(HashManifest $manifest): string;

    /**
     * @throws \InvalidArgumentException when the text cannot be parsed
     */
    public function parse(string $text): HashManifest;
}
