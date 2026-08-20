<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ParsedSourceCacheInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;
use WeakMap;

/**
 * Non-owning scan-result cache keyed by SourceCode object via a WeakMap. A
 * cached entry is reclaimed automatically once its SourceCode is no longer
 * referenced anywhere, so scanning a large tree of distinct sources cannot
 * exhaust memory through this cache. Within one operation the SAME SourceCode
 * instance is reused, so detect → rewrite → SemConv incur exactly one
 * parse+traverse per distinct source.
 */
final readonly class ParsedSourceCache implements ParsedSourceCacheInterface
{
    /** @var WeakMap<SourceCodeInterface, list<LiteralInterface>> */
    private WeakMap $store;

    public function __construct()
    {
        $this->store = new WeakMap();
    }

    public function has(SourceCodeInterface $code): bool
    {
        return $this->store->offsetExists($code);
    }

    /** @return list<LiteralInterface>|null */
    public function get(SourceCodeInterface $code): ?array
    {
        return $this->store->offsetExists($code)
            ? $this->store->offsetGet($code)
            : null;
    }

    /** @param list<LiteralInterface> $literals */
    public function put(SourceCodeInterface $code, array $literals): void
    {
        $this->store->offsetSet($code, $literals);
    }
}
