<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use function error_reporting;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

use function parse_ini_string;

/**
 * Parses INI configuration content using the standard `parse_ini_string`.
 *
 * Uses the typed scanner so values keep their native PHP types. Never performs
 * merging or validation. Implements the {@see ConfigurationLoaderInterface}
 * port and translates engine failures into the domain {@see ParseException}.
 */
final readonly class IniLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function supports(Format $format): bool
    {
        return Format::INI === $format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        $previous = error_reporting(\E_ERROR);
        $parsed = @parse_ini_string($raw, true, \INI_SCANNER_TYPED);
        error_reporting($previous);

        if (false === $parsed) {
            throw new ParseException('Invalid INI configuration.');
        }

        return $this->documentFactory->build($parsed, Format::INI);
    }
}
