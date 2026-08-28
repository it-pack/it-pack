<?php

declare(strict_types=1);

namespace Tests\Unit\Wiring;

use Codeception\Test\Unit;

/**
 * Architecture guard: injectable collaborators must arrive via constructors from
 * the composition root — they must not be default-constructed inside another
 * service's constructor (NV10).
 *
 * The NV10 inventory (ConfigurationResolver, IncludeProcessor,
 * NativeConfigurationSchema, ConfigurationDiffer, ConfigurationDriftDetector,
 * FileSystemCache) had constructor parameter defaults like
 * `= new ConfigurationSnapshotFactory()`. Those defaults were removed; this
 * test fails the build (not just lint) if a `= new` reappears in a constructor
 * parameter default of any of those classes, so the policy cannot silently
 * regress.
 *
 * Value objects are the carve-out (they *are* data and may be constructed
 * inline); none of the inventory classes default-construct a value object, so
 * the blessed list is empty here. See ADR-000Y "Instantiation policy & the
 * value-object carve-out" for the full rationale.
 */
class NoInjectableConstructorDefaultsTest extends Unit
{
    public function testNoInjectableConstructorDefaultsExist(): void
    {
        $offending = [];

        foreach ($this->inventoryFiles() as $file) {
            if (!is_file($file)) {
                continue;
            }

            $source = file_get_contents($file);

            if (false === $source) {
                continue;
            }

            if ($this->hasConstructorDefaultNew($source)) {
                $offending[] = $file;
            }
        }

        verify($offending)->equals([]);
    }
    /**
     * @return iterable<string> absolute paths of the NV10 inventory files.
     */
    private function inventoryFiles(): iterable
    {
        $root = dirname(__DIR__, 3) . '/src';

        yield $root . '/Core/ConfigurationResolver.php';
        yield $root . '/Core/IncludeProcessor.php';
        yield $root . '/Core/ConfigurationDiffer.php';
        yield $root . '/Core/ConfigurationDriftDetector.php';
        yield $root . '/Cache/FileSystemCache.php';
        yield $root . '/Adapter/NativeConfigurationSchema.php';
    }

    private function hasConstructorDefaultNew(string $source): bool
    {
        $tokens = token_get_all($source);
        $depth = 0;
        $inParams = false;
        $startDepth = 0;
        $prevEquals = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];

                if (T_FUNCTION === $id || T_FN === $id) {
                    $inParams = true;
                    $startDepth = $depth;
                }

                if (T_NEW === $id && $inParams && $prevEquals) {
                    return true;
                }

                if (T_WHITESPACE !== $id && T_DOC_COMMENT !== $id && T_COMMENT !== $id) {
                    $prevEquals = false;
                }

                continue;
            }

            $char = $token;

            if ('(' === $char) {
                $depth++;
            } elseif (')' === $char) {
                $depth--;

                if ($inParams && $depth === $startDepth) {
                    $inParams = false;
                }
            } elseif ('=' === $char) {
                $prevEquals = true;
            } elseif ('(' !== $char && ')' !== $char) {
                $prevEquals = false;
            }
        }

        return false;
    }
}
