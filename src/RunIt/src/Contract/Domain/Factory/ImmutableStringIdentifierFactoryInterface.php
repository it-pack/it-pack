<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain\Factory;

use ItPack\RunIt\Contract\Domain\ImmutableStringIdentifierInterface;

interface ImmutableStringIdentifierFactoryInterface extends StringIdentifierFactoryInterface
{
    public function build(): ImmutableStringIdentifierInterface;
}
