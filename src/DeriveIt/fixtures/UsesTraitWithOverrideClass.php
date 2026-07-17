<?php

declare(strict_types=1);

namespace _Fixture;

class UsesTraitWithOverrideClass
{
	use ConflictingMethodTrait;

	public function overridden(): string
	{
		return "from class";
	}
}
