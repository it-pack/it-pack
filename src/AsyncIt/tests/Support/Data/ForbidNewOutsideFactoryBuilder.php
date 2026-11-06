<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\RuleFixture;

use ItPack\AsyncIt\Application\Factory\FiberFactory as ProjectFactory;
use Nyholm\Psr7\Request as VendorRequest;
use WeakMap as AliasedMap;

function constructClasses(): void
{
    new \WeakMap();
    new AliasedMap();
    new \DateTimeImmutable();
    new ProjectFactory();
    new VendorRequest('GET', '/');
    new MissingClass();
    new UserlandInternal();
}

/** @internal */
class UserlandInternal extends \stdClass
{
}
