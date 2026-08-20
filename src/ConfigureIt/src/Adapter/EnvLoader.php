<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Parses the raw "KEY=VALUE" body produced by {@see EnvReader} into a flat
 * document.
 *
 * Deliberately dumb, symmetric with {@see JsonLoader} and {@see PhpLoader}:
 * decodes syntax only. Keys are kept flat (no dot/underscore nesting) and
 * values stay strings — no type coercion. Name-mapping (flat env key to
 * nested config path) and type casting (string to bool/int/list/...) are
 * later pipeline concerns, not this loader's job.
 *
 * Implements the `ConfigurationLoader` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Loader"
 */
final readonly class EnvLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function supports(Format $format): bool
    {
        return $format === Format::ENV;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        $data = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separatorPosition = strpos($line, '=');

            if ($separatorPosition === false) {
                throw new ParseException(sprintf('Invalid ENV line, expected "KEY=VALUE": "%s".', $line));
            }

            $key = trim(substr($line, 0, $separatorPosition));

            if ($key === '') {
                throw new ParseException(sprintf('Invalid ENV line, empty key: "%s".', $line));
            }

            $data[$key] = substr($line, $separatorPosition + 1);
        }

        return $this->documentFactory->build($data, Format::ENV);
    }
}