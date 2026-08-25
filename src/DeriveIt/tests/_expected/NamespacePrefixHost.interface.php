<?php

namespace _Fixture;

interface NamespacePrefixHostInterface
{
	function useOwned(\Prefix\OwnedService $service): void;


	function useExternal(\Other\VendorThing $thing): void;
}
