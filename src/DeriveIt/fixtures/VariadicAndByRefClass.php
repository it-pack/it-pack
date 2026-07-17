<?php

declare(strict_types=1);

namespace _Fixture;

class VariadicAndByRefClass
{
	public function combine(string ...$parts): array
	{
	}


	public function increment(int &$counter): void
	{
	}
}
