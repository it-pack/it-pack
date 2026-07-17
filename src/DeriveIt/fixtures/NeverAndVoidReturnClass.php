<?php

declare(strict_types=1);

namespace _Fixture;

class NeverAndVoidReturnClass
{
	public function alwaysThrows(): never
	{
	}


	public function noReturnValue(): void
	{
	}
}
