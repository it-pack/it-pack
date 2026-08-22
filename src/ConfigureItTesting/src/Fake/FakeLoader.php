<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Fake;

use Closure;
use ItPack\ConfigureIt\Contract\ConfigurationDocumentInterface;
use ItPack\ConfigureIt\Contract\ConfigurationLoaderInterface;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Configurable {@see ConfigurationLoaderInterface} for tests.
 *
 * By default it parses JSON. Supply a custom `$parser` closure to support other
 * shapes (e.g. INI, custom DSL) and restrict `$formats` to the formats it
 * claims to support.
 */
final class FakeLoader implements ConfigurationLoaderInterface
{
    /** @var array<Format> */
    private array $formats;

    /** @var Closure(string): array<string, mixed> */
    private Closure $parser;

    /**
     * @param Closure(string): array<string, mixed>|null $parser
     */
    public function __construct(?Closure $parser = null, Format ...$formats)
    {
        $this->parser = $parser ?? static fn (string $raw): array => (array) json_decode($raw, true);
        $this->formats = [] === $formats ? Format::cases() : $formats;
    }

    public function supports(Format $format): bool
    {
        return in_array($format, $this->formats, true);
    }

    public function load(string $raw): ConfigurationDocumentInterface
    {
        /** @var array<string, mixed> $data */
        $data = ($this->parser)($raw);

        return new ConfigurationDocument($data, Format::PHP, null, self::class);
    }
}
