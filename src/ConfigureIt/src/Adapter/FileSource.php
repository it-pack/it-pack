<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResourceFactory;
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
            Format::PHP->value => Format::PHP->value,
            Format::JSON->value => Format::JSON->value,
        ],
        private ConfigurationResourceFactory $resourceFactory = new ConfigurationResourceFactory(),
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

        $paths = array_map(
            fn (\SplFileInfo $file) => $file->getPathname(),
            array_filter(
                iterator_to_array(
                    new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator(
                            $this->directory,
                            \FilesystemIterator::SKIP_DOTS,
                        )
                    )
                ),
                function (\SplFileInfo $file) {
                    return !(
                        !$file->isFile() || !array_key_exists(
                            strtolower(
                                $file->getExtension()
                            ),
                            $this->extensions
                        )
                    );
                }
            ),
        );

        sort($paths);

        foreach ($paths as $path) {
            yield $this->resourceFactory->build(
                $path,
                Format::fromString(
                    $this->extensions[
                        strtolower(
                            pathinfo(
                                $path,
                                PATHINFO_EXTENSION
                            )
                        )
                    ]
                )
            );
        }
    }
}
