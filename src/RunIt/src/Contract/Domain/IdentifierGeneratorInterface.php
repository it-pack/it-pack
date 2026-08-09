<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface IdentifierGeneratorInterface
{
    public function generate(): string;
}
