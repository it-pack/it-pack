<?php

declare(strict_types=1);

namespace _Fixture;

class SelfStaticParentReturnClass extends ConcreteChildClass
{
	public function withSelf(): self
	{
	}


	public function withStatic(): static
	{
	}


	public function toParent(): parent
	{
	}
}
