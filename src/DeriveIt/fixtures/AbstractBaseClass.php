<?php

declare(strict_types=1);

namespace _Fixture;

abstract class AbstractBaseClass
{
	public function concreteBaseMethod(): void
	{
	}


	abstract public function abstractMethod(): string;


	protected function protectedHelper(): void
	{
	}
}
