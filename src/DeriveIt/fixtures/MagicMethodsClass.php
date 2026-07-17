<?php

declare(strict_types=1);

namespace _Fixture;

class MagicMethodsClass
{
	public function __get(string $name): mixed
	{
	}


	public function __set(string $name, mixed $value): void
	{
	}


	public function __call(string $name, mixed $value): mixed
	{
	}


	public function __toString(): string
	{
	}


	public function __invoke(): mixed
	{
	}
}
