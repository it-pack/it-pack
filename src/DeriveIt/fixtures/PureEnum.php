<?php

declare(strict_types=1);

namespace _Fixture;

enum PureEnum
{
	case FOO;
	case BAR;
	case BAZ;

	public function label(): string
	{
		return match ($this) {
		    self::FOO => 'Foo label',
		    self::BAR => 'Bar label',
		    self::BAZ => 'Baz label',
		};
	}
}
