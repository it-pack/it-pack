<?php

declare(strict_types=1);

namespace _Fixture;

class UsesMultipleTraitsClass
{
	use SimpleTrait;
	use ConflictingMethodTrait;

	public function ownMethod(): void
	{
	}
}
