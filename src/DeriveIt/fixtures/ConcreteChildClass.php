<?php

declare(strict_types=1);

namespace _Fixture;

class ConcreteChildClass extends AbstractBaseClass
{
	public function abstractMethod(): string
	{
		return "implemented";
	}


	public function childOnlyMethod(): void
	{
	}
}
