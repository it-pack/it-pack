<?php

declare(strict_types=1);

namespace _Fixture;

class NullableTypeClass
{
	public function find(?string $id = null): ?self
	{
	}
}
