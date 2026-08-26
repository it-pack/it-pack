<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use function get_class;
use function is_file;

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
use ItPack\ConfigureIt\ValueObject\Format;

use function pathinfo;
use function strtolower;

/**
 * Builds a resolved {@see ConfigurationGraphInterface} from a single configuration
 * file, using the public reader/loader/normalizer ports. Shared by the CLI
 * commands so each subcommand stays a thin adapter over Core services.
 *
 * @see docs/architecture/GRAPH.md "Developer Experience"
 */
final class GraphFromFile
{
    /**
     * @param array<string, string> $extensions map of file extension => format value.
     */
    private const EXTENSIONS = [
        'php' => 'php',
        'json' => 'json',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'toml' => 'toml',
        'ini' => 'ini',
        'xml' => 'xml',
        'env' => 'dotenv',
        'md' => 'frontmatter',
        'markdown' => 'frontmatter',
    ];

    public static function build(string $path): ConfigurationGraphInterface
    {
        if (!is_file($path)) {
            throw new ResolutionException(sprintf('Configuration file "%s" does not exist.', $path));
        }

        $format = self::formatForPath($path);
        $resource = new ConfigurationResourceFactory()->build($path, $format);

        $raw = new FileReader()->read($resource);
        $loader = new ConfigurationLoaderFactory(ConfigurationLoaders::default())->build($format);
        $parsed = $loader->load($raw);

        $document = new ConfigurationDocument(
            $parsed->toArray(),
            $parsed->format(),
            $path,
            get_class($loader),
        );

        return new ConfigurationGraph(new DocumentNormalizer()->normalize($document, 1, $path));
    }

    private static function formatForPath(string $path): Format
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!isset(self::EXTENSIONS[$extension])) {
            throw new ResolutionException(sprintf('Unsupported configuration extension "%s".', $extension));
        }

        return Format::fromString(self::EXTENSIONS[$extension]);
    }
}
