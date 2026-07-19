<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;

/**
 * Reads environment variables into a raw, newline-delimited "KEY=VALUE" body
 * so that a loader can parse it uniformly.
 *
 * Knows how to read ENV; never parses format. Implements the
 * `ConfigurationReader` port.
 *
 * @see docs/architecture/MODEL.md "Configuration Reader"
 */
final readonly class EnvReader implements ConfigurationReaderInterface
{
    /**
     * @param list<string>|null $only when set, only these variable names are read.
     */
    public function __construct(
        private ?array $only = null,
    ) {
    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        if ($resource->format() !== \ItPack\ConfigureIt\ValueObject\Format::ENV) {
            throw new ReadException(sprintf('EnvReader cannot read format "%s".', $resource->format()->value));
        }

        $lines = [];

        /** @var array<string, string> $environment */
        $environment = $_ENV;

        foreach ($environment as $key => $value) {
            if ($this->only !== null && !in_array($key, $this->only, true)) {
                continue;
            }

            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines);
    }
}
