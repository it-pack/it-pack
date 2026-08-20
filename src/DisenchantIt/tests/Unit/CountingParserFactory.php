<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use PhpParser\Parser;
use PhpParser\ParserFactory as NikicPhpParserFactory;

/**
 * Test double for ParserFactoryInterface that counts how many times a parser
 * is actually materialized. Used to prove the Detector builds the (expensive)
 * nikic parser lazily and reuses it across scans.
 */
final class CountingParserFactory implements ParserFactoryInterface
{
    public int $builds = 0;

    public function build(): Parser
    {
        $this->builds++;

        return new NikicPhpParserFactory()->createForNewestSupportedVersion();
    }
}
