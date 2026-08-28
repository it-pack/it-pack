<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use PhpParser\Parser;
use PhpParser\ParserFactory as NikicPhpParserFactory;

final readonly class NikicParserFactory implements ParserFactoryInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function build(): Parser
    {
        return new NikicPhpParserFactory()->createForNewestSupportedVersion();
    }
}
