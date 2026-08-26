<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Exception\ParseException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Parses raw content into a configuration document.
 *
 * A loader understands syntax and nothing else. It must declare which format
 * it supports and never perform merging, validation or variable resolution.
 *
 * @see docs/architecture/MODEL.md "Configuration Loader"
 */
interface ConfigurationLoaderInterface
{
    /**
     * A short, stable identifier for this loader (e.g. "json-loader").
     *
     * Replaces runtime {@see get_class()} discrimination so telemetry, cache
     * fingerprints and document source labels stay stable across refactors and
     * do not leak fully-qualified class names into persisted artifacts.
     */
    public function name(): string;

    public function supports(Format $format): bool;

    /**
     * @throws ParseException when the raw content cannot be parsed.
     */
    public function load(string $raw): ConfigurationDocumentInterface;
}
