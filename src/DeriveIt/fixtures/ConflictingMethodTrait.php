<?php

declare(strict_types=1);

namespace _Fixture;

trait ConflictingMethodTrait
{
	public function overridden(): string
	{
		return "from trait";
	}
}
