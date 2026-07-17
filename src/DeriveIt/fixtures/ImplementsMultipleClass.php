<?php

declare(strict_types=1);

namespace _Fixture;

class ImplementsMultipleClass implements PreExistingInterface, SecondPreExistingInterface
{
	public function fromInterfaceOne(): void
	{
	}


	public function fromInterfaceTwo(): void
	{
	}


	public function ownMethod(): void
	{
	}
}
