<?php

declare(strict_types=1);

namespace ItPack\Qa\PhpArkitect;

use Arkitect\ClassSet;
use Arkitect\RuleBuilders\Architecture\Architecture;

require_once __DIR__ . '/forbid-sibling.php';

/**
 * Canonical phparkitect helpers shared by every it-pack package.
 *
 * Packages require this file from their own `phparkitect.php` config and call
 * the helpers below to build the consolidated architecture rules. This keeps the
 * hexagonal layer contract and the "ports-not-imports" doctrine identical across
 * the monorepo instead of copy-pasted (and drifting) per package.
 *
 * Reference consumer: src/DisenchantIt/phparkitect.php.
 */

/**
 * Build the standard hexagonal layer rules for a package.
 *
 * @param array<string,string> $components      component name => namespace glob
 *                                              relative to $root, e.g.
 *                                              ['Domain' => 'Domain\*'].
 * @param array<string,string[]> $dependencies  component name => list of
 *                                              components it may depend on.
 *
 * @return \Arkitect\Rules\ArchRule[]
 */
function layeredArchitecture(string $root, array $components, array $dependencies): array
{
    $arch = Architecture::withComponents();

    foreach ($components as $name => $glob) {
        $arch = $arch->component($name)->definedBy($root . '\\' . $glob);
    }

    foreach ($dependencies as $from => $mayDependOn) {
        $arch = $arch->where($from)->mayDependOnComponents(...$mayDependOn);
    }

    return iterator_to_array($arch->rules());
}

/**
 * Convenience wrapper: scan a package's source tree into a ClassSet.
 */
function classSet(string $srcDir): ClassSet
{
    return ClassSet::fromDir($srcDir);
}
