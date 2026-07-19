<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Parses JSON configuration content.
 *
 * Uses the standard `ext-json`; never performs merging or validation. Implements
 * the `ConfigurationLoader` port.
 */
final class JsonLoader implements ConfigurationLoaderInterface
{
    public function supports(Format $format): bool
    {
        return $format === Format::JSON;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ParseException(sprintf('Invalid JSON configuration: %s', $e->getMessage()), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new ParseException('JSON configuration must decode to an object/array.');
        }

        /** @var array<string, mixed> $data */
        $data = $decoded;

        return new ConfigurationDocument($data, Format::JSON);
    }
}
