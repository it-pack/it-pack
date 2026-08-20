<?php

declare(strict_types=1);

namespace _Fixture;

class NamespacePrefixHost
{
    public function useOwned(\Prefix\OwnedService $service): void
    {
    }

    public function useExternal(\Other\VendorThing $thing): void
    {
    }
}
