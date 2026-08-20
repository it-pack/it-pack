<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\MagicLiteralVisitor;
use ItPack\DisenchantIt\Adapter\NamingContextExtractor;
use ItPack\DisenchantIt\Contract\MagicLiteralVisitorInterface;
use ItPack\DisenchantIt\Contract\MagicStringVisitorFactoryInterface;
use ItPack\DisenchantIt\Contract\NamingContextExtractorInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;

final readonly class MagicStringVisitorFactory implements MagicStringVisitorFactoryInterface
{
    public function __construct(
        private readonly NamingContextExtractorInterface $extractor = new NamingContextExtractor(),
    ) {
    }

    public function build(RoleClassifierInterface $classifier, string $code): MagicLiteralVisitorInterface
    {
        return new MagicLiteralVisitor($classifier, $this->extractor, $code);
    }

    public static function create(): self
    {
        return new self();
    }
}
