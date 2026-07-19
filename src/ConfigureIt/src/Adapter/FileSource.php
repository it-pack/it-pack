<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Discovers configuration files in a directory (recursively) and returns them
 * as resources keyed by detected format.
 *
 * Discovery only — never reads or parses. Implements the `ConfigurationSource`
 * port; isolates filesystem traversal in the adapter layer (ADR-0002).
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 */
final readonly class FileSource implements ConfigurationSourceInterface
{
    /**
     * @param array<string, string> $extensions map of file extension => format value.
     */
    public function __construct(
        private string $directory,
        private array  $extensions = [
            'php' => Format::PHP->value,
            'json' => Format::JSON->value,
        ],
    ) {
    }

    /**
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $directory = new \RecursiveDirectoryIterator(
            $this->directory,
            \FilesystemIterator::SKIP_DOTS,
        );
        $iterator = new \RecursiveIteratorIterator($directory);

        $paths = [];

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if (!array_key_exists($extension, $this->extensions)) {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        sort($paths);

        foreach ($paths as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            yield new ConfigurationResource($path, Format::fromString($this->extensions[$extension]));
        }
    }
}
