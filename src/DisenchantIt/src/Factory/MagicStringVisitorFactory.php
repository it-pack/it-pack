<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\MagicStringVisitor;

final readonly class MagicStringVisitorFactory implements MagicStringVisitorFactoryInterface
{
    public function build(RoleClassifierInterface $classifier): MagicStringVisitorInterface
    {
        return new MagicStringVisitor($classifier);
    }

    public static function create(): self
    {
        return new self();
    }
}
