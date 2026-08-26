<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Parses raw ".env" content into a flat configuration document.
 *
 * Uses the `vlucas/phpdotenv` library behind the `Dotenv` port: quoting,
 * comments, and `${VAR}` interpolation are handled by the vendor parser, which
 * is invoked through {@see Dotenv::parse()} so the host environment is never
 * mutated. Vendor failures are translated to the domain {@see ParseException}.
 *
 * Implements the `ConfigurationLoader` port; never reads files or merges data.
 *
 * @see docs/architecture/MODEL.md "Configuration Loader"
 */
final readonly class DotenvLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function supports(Format $format): bool
    {
        return Format::DOTENV === $format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        try {
            $data = Dotenv::parse($raw);
        } catch (InvalidFileException $exception) {
            throw new ParseException($exception->getMessage(), 0, $exception);
        }

        return $this->documentFactory->build($data, Format::DOTENV);
    }
}
