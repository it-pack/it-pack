<?php

namespace _Fixture;

interface UnionTypeClassInterface
{
	function queryLeaf(string $key): array|string|int|float|bool|null;
}
