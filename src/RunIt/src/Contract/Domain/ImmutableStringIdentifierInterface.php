<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface ImmutableStringIdentifierInterface extends StringIdentifierInterface
{
    public function withID(string $ide): self;
}
