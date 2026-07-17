<?php

declare(strict_types=1);

namespace _Fixture;

class FinalMethodsInNonFinalClass
{
	final public function cannotOverride(): void
	{
	}


	public function canOverride(): void
	{
	}
}
