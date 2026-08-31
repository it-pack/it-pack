<?php

declare(strict_types=1);

namespace ItPack\Qa\PhpArkitect;

use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Rules\ArchRule;
use Arkitect\Rules\Rule;

/**
 * Reusable rule generator: forbid any class in $namespace from depending on
 * $forbiddenPrefix (a namespace glob, e.g. 'ItPack\RunIt\*').
 *
 * This encodes the it-pack doctrine that consumers wire RunIt in through a
 * bridge package (it-pack/run-it-<consumer> / <consumer>-run-it) rather than
 * importing ItPack\RunIt\* directly. Mirrors DisenchantIt's Core rule
 * (src/DisenchantIt/phparkitect.php).
 *
 * @return ArchRule
 */
function forbidSibling(string $namespace, string $forbiddenPrefix, string $because): ArchRule
{
    return Rule::namespace($namespace)
        ->should(new NotDependsOnTheseNamespaces([$forbiddenPrefix]))
        ->because($because);
}
