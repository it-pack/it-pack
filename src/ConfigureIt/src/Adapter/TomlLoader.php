<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use function class_exists;

use Internal\Toml\Toml;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentFactoryInterface;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;
use Throwable;

/**
 * Parses TOML configuration content using `internal/toml`.
 *
 * Never performs merging or validation. Implements the
 * {@see ConfigurationLoaderInterface} port and translates engine failures into
 * the domain {@see ParseException}.
 */
final readonly class TomlLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private ConfigurationDocumentFactoryInterface $documentFactory = new ConfigurationDocumentFactory(),
    ) {
    }

    public function name(): string
    {
        return 'toml-loader';
    }
    public function supports(Format $format): bool
    {
        return Format::TOML === $format;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        if (!class_exists(Toml::class)) {
            throw new ParseException('TOML support requires the internal/toml package.');
        }

        try {
            $parsed = Toml::parseToArray($raw);
        } catch (Throwable $e) {
            throw new ParseException(sprintf('Invalid TOML configuration: %s', $e->getMessage()), 0, $e);
        }

        return $this->documentFactory->build($parsed, Format::TOML);
    }
}
