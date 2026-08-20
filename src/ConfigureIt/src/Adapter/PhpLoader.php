<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Contract\TempFileFactoryInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Core\TempFileFactory;
use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Parses a PHP configuration file that returns an associative array.
 *
 * Isolates the `require` of a `<?php return [...]` file in the adapter layer
 * (ADR-0002). Implements the `ConfigurationLoader` port.
 */
final class PhpLoader implements ConfigurationLoaderInterface
{
    public const string TEMP_FILE_PREFIX = 'php-cfg-';

    public function __construct(
        private readonly TempFileFactoryInterface $tempFileFactory = new TempFileFactory(
            self::TEMP_FILE_PREFIX,
        )
    ) {
    }

    public function supports(Format $format): bool
    {
        return $format === Format::PHP;
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        $temp = $this->tempFileFactory->require();

        try {
            if (file_put_contents($temp, $raw) === false) {
                throw new ParseException('Unable to write temporary PHP configuration.');
            }

            $returned = require $temp;

            if (!is_array($returned)) {
                throw new ParseException('PHP configuration must return an array.');
            }

            /** @var array<string, mixed> $data */
            $data = $returned;

            return new ConfigurationDocument($data, Format::PHP);
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
