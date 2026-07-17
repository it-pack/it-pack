<?php

declare(strict_types=1);

namespace _Fixture;

class UnionTypeClass
{
	public int|string|null $value;


	public function queryLeaf(string $key): null|array|string|int|float|bool
	{
	}
}
