<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

/**
 * Single place that assembles Nette InterfaceType objects into a renderable
 * PHP source string (maintainability F1). Every deriver previously inlined the
 * same PhpNamespace + PhpFile dance; this consolidates it so the rendering
 * contract lives in exactly one spot.
 */
final class SourceRenderer
{
    public function render(InterfaceType $interface, string $namespace): string
    {
        return $this->renderMany([$interface], $namespace);
    }

    /**
     * @param list<InterfaceType> $interfaces
     */
    public function renderMany(array $interfaces, string $namespace): string
    {
        $ns = new PhpNamespace($namespace);
        foreach ($interfaces as $interface) {
            $ns->add($interface);
        }

        $file = new PhpFile();
        $file->addNamespace($ns);

        return (string) $file;
    }
}
