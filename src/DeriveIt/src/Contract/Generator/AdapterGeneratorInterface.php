<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Generator;

use Nette\PhpGenerator\ClassType;

/**
 * Generates a delegating adapter that implements a previously derived interface
 * and wraps a concrete adaptee (F2 / R.2). Extracted so "an adapter generator" is
 * polymorphic and assemblable by {@see \ItPack\DeriveIt\DeriveItFactory} at the
 * composition root, closing the hexagonal gap for the secondary derivers.
 */
interface AdapterGeneratorInterface
{
    /**
     * Build and return the generated delegating adapter ({@see ClassType}).
     */
    public function generate(): ClassType;

    /**
     * Render the derived interface and the generated adapter together as a single
     * self-contained PHP source string.
     */
    public function generateSource(): string;
}
