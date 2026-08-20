<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\MagicLiteralVisitorInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\MagicLiteralVisitor;

final readonly class MagicStringVisitorFactory implements MagicStringVisitorFactoryInterface
{
    public function build(RoleClassifierInterface $classifier, string $code): MagicLiteralVisitorInterface
    {
        return new MagicLiteralVisitor($classifier, $code);
    }

    public static function create(): self
    {
        return new self();
    }
}
