<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\DeriveMode;

/**
 * Declarative argv → {@see DeriveCliInput} parser (F5 / R.5). Replaces the
 * monolithic 25-case switch that used to live in {@see DeriveCommand::parse()} with
 * a single option table, so adding a CLI flag is a one-row change and the allowed
 * mode set lives only in the {@see DeriveMode} enum.
 */
final class ArgParser
{
    /**
     * Number of value arguments each option consumes; absent entries are boolean
     * flags. Kept as a map co-located with {@see self::table()} so adding an
     * option is one table row plus (when value-consuming) one arity entry —
     * replacing the previous duplicate VALUE_OPTIONS name list (F7 / R3-8).
     *
     * @return array<string, int>
     */
    private static function arities(): array
    {
        return [
            'mode' => 1,
            'out' => 1,
            'root' => 1,
            'preset' => 1,
            'ns-prefix' => 1,
            'wrapper' => 1,
            'skip' => 1,
            'only' => 1,
            'subst' => 1,
            'rename' => 1,
            'owned-attr' => 1,
            're-expose' => 1,
            'base-dir' => 1,
        ];
    }

    public function parse(array $args): DeriveCliInput
    {
        $state = new ParseState();

        for ($i = 0; $i < count($args); ++$i) {
            $arg = $args[$i];
            if ($arg === '' || $arg[0] !== '-') {
                $state->targets[] = $arg;

                continue;
            }

            $body = substr($arg, $arg[1] === '-' ? 2 : 1);
            $value = null;
            if (str_contains($body, '=')) {
                [$body, $value] = explode('=', $body, 2);
            }
            if ($value === null && (self::arities()[$body] ?? 0) > 0 && isset($args[$i + 1]) && $args[$i + 1][0] !== '-') {
                $value = $args[$i + 1];
                ++$i;
            }

            $this->apply($body, $value, $state);
        }

        return new DeriveCliInput(
            mode: $state->mode,
            out: $state->out,
            root: $state->root,
            baseDir: $state->baseDir,
            targets: $state->targets,
            config: $this->buildConfig($state),
            namespacePrefixMap: $state->namespacePrefixMap,
            wrapperMap: $state->wrapperMap,
        );
    }

    private function apply(string $body, ?string $value, ParseState $state): void
    {
        $handler = self::table()[$body] ?? null;
        if ($handler === null) {
            throw new \InvalidArgumentException(sprintf('unknown option "--%s".', $body));
        }

        $handler($value, $state);
    }

    /**
     * @return array<string, \Closure(string|null, ParseState): void>
     */
    private static function table(): array
    {
        return [
            'mode' => static function (?string $v, ParseState $s): void {
                $raw = self::requireValue($v, 'mode');
                $mode = DeriveMode::tryFrom($raw);
                if ($mode === null) {
                    throw new \InvalidArgumentException(sprintf('unknown mode "%s" (expected flat|per-trait|adapter|family|family-adapter).', $raw));
                }
                $s->mode = $mode;
            },
            'out' => static function (?string $v, ParseState $s): void {
                $s->out = self::requireValue($v, 'out');
            },
            'base-dir' => static function (?string $v, ParseState $s): void {
                $s->baseDir = self::requireValue($v, 'base-dir');
            },
            'root' => static function (?string $v, ParseState $s): void {
                $s->root = self::requireValue($v, 'root');
            },
            'preset' => static function (?string $v, ParseState $s): void {
                $raw = self::requireValue($v, 'preset');
                $preset = Preset::tryFrom($raw);
                if ($preset === null) {
                    throw new \InvalidArgumentException(sprintf(
                        'unknown preset "%s" (expected %s).',
                        $raw,
                        implode('|', Preset::values()),
                    ));
                }
                $s->preset = $preset;
            },
            'ns-prefix' => static function (?string $v, ParseState $s): void {
                $s->namespacePrefixMap = [...$s->namespacePrefixMap, ...self::parsePairs(self::requireValue($v, 'ns-prefix'))];
            },
            'wrapper' => static function (?string $v, ParseState $s): void {
                $s->wrapperMap = [...$s->wrapperMap, ...self::parsePairs(self::requireValue($v, 'wrapper'))];
            },
            'skip' => static function (?string $v, ParseState $s): void {
                $s->skip = [...$s->skip, ...self::splitList(self::requireValue($v, 'skip'))];
            },
            'only' => static function (?string $v, ParseState $s): void {
                $s->only = [...$s->only, ...self::splitList(self::requireValue($v, 'only'))];
            },
            'subst' => static function (?string $v, ParseState $s): void {
                $s->subst = [...$s->subst, ...self::parsePairs(self::requireValue($v, 'subst'))];
            },
            'rename' => static function (?string $v, ParseState $s): void {
                $s->rename = [...$s->rename, ...self::parsePairs(self::requireValue($v, 'rename'))];
            },
            'owned-attr' => static function (?string $v, ParseState $s): void {
                $s->ownedAttributes = [...$s->ownedAttributes, ...self::splitList(self::requireValue($v, 'owned-attr'))];
            },
            're-expose' => static function (?string $v, ParseState $s): void {
                $s->reExpose = [...$s->reExpose, ...self::splitList(self::requireValue($v, 're-expose'))];
            },
            'include-static' => static function (?string $v, ParseState $s): void {
                $s->includeStatic = true;
            },
            'include-magic' => static function (?string $v, ParseState $s): void {
                $s->includeMagic = true;
            },
            'include-constants' => static function (?string $v, ParseState $s): void {
                $s->includeConstants = true;
            },
            'protected' => static function (?string $v, ParseState $s): void {
                $s->protected = true;
            },
            'preserve-docblocks' => static function (?string $v, ParseState $s): void {
                $s->preserveDocblocks = true;
            },
            'audit-leakage' => static function (?string $v, ParseState $s): void {
                $s->auditLeakage = true;
            },
            'fail-on-empty' => static function (?string $v, ParseState $s): void {
                $s->failOnEmpty = true;
            },
            'attribute-pass-through' => static function (?string $v, ParseState $s): void {
                $s->attributePassThrough = true;
            },
            'throws-capture' => static function (?string $v, ParseState $s): void {
                $s->throwsCapture = true;
            },
        ];
    }

    private function buildConfig(ParseState $s): DeriveConfig
    {
        // The preset (when given) selects the starting config; explicit flags are
        // applied on top of it below, so a flag always wins over its preset default.
        $config = $s->preset?->toConfig() ?? new DeriveConfig();

        if ($s->protected) {
            $config = $config->withVisibility(DeriveConfig::VISIBILITY_PROTECTED);
        }
        if ($s->includeStatic) {
            $config = $config->withIncludeStatic(true);
        }
        if ($s->includeMagic) {
            $config = $config->withIncludeMagic(true);
        }
        if ($s->includeConstants) {
            $config = $config->withIncludeConstants(true);
        }
        if ($s->preserveDocblocks) {
            $config = $config->withPreserveDocblocks(true);
        }
        if ($s->auditLeakage) {
            $config = $config->withAuditLeakage(true);
        }
        if ($s->failOnEmpty) {
            $config = $config->withFailOnEmpty(true);
        }
        if ($s->attributePassThrough) {
            $config = $config->withAttributePassThrough(true);
        }
        if ($s->throwsCapture) {
            $config = $config->withThrowsCapture(true);
        }
        if ($s->skip !== []) {
            $config = $config->withSkipMethods($s->skip);
        }
        if ($s->only !== []) {
            $config = $config->withOnlyMethods($s->only);
        }
        if ($s->subst !== []) {
            $config = $config->withTypeSubstitutionMap($s->subst);
        }
        if ($s->rename !== []) {
            $config = $config->withMethodRenames($s->rename);
        }
        if ($s->ownedAttributes !== []) {
            $config = $config->withOwnedAttributes($s->ownedAttributes);
        }
        if ($s->reExpose !== []) {
            $config = $config->withReExposeOwnedInterfaces($s->reExpose);
        }

        return $config;
    }

    private static function requireValue(?string $value, string $name): string
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException(sprintf('--%s requires a value.', $name));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }

    /**
     * @return array<string, string>
     */
    private static function parsePairs(string $value): array
    {
        $pairs = [];
        foreach (self::splitList($value) as $pair) {
            if (!str_contains($pair, '=')) {
                throw new \InvalidArgumentException(sprintf('expected key=value, got "%s".', $pair));
            }
            [$k, $v] = explode('=', $pair, 2);
            $pairs[trim($k)] = trim($v);
        }

        return $pairs;
    }
}
