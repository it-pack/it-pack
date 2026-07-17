<?php

declare(strict_types=1);

namespace _Fixture;

enum EnumImplementingInterface: string implements PreExistingInterface
{
	case ONE = 'one';
	case TWO = 'two';

	public function fromInterfaceOne(): void
	{
	}
}
