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
 * Parses YAML configuration content using the standard `ext-yaml` extension.
 *
 * Never performs merging or validation. Implements the
 * {@see ConfigurationLoaderInterface} port and translates engine failures into
 * the domain {@see ParseException}.
 */
final readonly class YamlLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function supports(Format $format): bool
    {
        return $format === Format::YAML;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        if (!\function_exists('yaml_parse')) {
            throw new ParseException('YAML support requires the ext-yaml extension.');
        }

        $previous = \error_reporting(\E_ERROR);
        $parsed = @\yaml_parse($raw);
        \error_reporting($previous);

        if ($parsed === false) {
            throw new ParseException('Invalid YAML configuration.');
        }

        if (!\is_array($parsed)) {
            throw new ParseException('YAML configuration must decode to a mapping/array.');
        }

        return $this->documentFactory->build($parsed, Format::YAML);
    }
}
