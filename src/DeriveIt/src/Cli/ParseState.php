<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Generator\DeriveMode;

/**
 * Mutable accumulator for {@see ArgParser} (F5 / R.5). Collects every parsed CLI
 * option so the declarative option table can mutate it without a giant switch.
 *
 * @internal
 */
final class ParseState
{
    public DeriveMode $mode = DeriveMode::FLAT;
    public ?string $out = null;
    public ?string $root = null;
    public ?Preset $preset = null;
    public ?string $baseDir = null;

    /** @var list<string> */
    public array $targets = [];

    /** @var array<string, string> */
    public array $namespacePrefixMap = [];

    /** @var array<string, string> */
    public array $wrapperMap = [];

    public bool $includeStatic = false;
    public bool $includeMagic = false;
    public bool $includeConstants = false;
    public bool $protected = false;
    public bool $preserveDocblocks = false;
    public bool $auditLeakage = false;
    public bool $failOnEmpty = false;
    public bool $attributePassThrough = false;
    public bool $throwsCapture = false;

    /** @var list<string> */
    public array $skip = [];

    /** @var list<string> */
    public array $only = [];

    /** @var array<string, string> */
    public array $subst = [];

    /** @var array<string, string> */
    public array $rename = [];

    /** @var list<string> */
    public array $ownedAttributes = [];

    /** @var list<string> */
    public array $reExpose = [];
}
