<?php

declare(strict_types=1);

namespace _Fixture;

enum SimpleBackedEnum: string
{
	case ALPINE = 'alpine';
	case DEBIAN = 'debian';
	case DEFAULT = 'default';
}
