<?php

declare(strict_types=1);

namespace _Fixture;

#[\Attribute(flags: 'Attribute::TARGET_CLASS')]
class AttributedClass
{
	#[\Deprecated(since: '2.0')]
	public function handle(
		#[\SensitiveParameter]
		$input,
	): void
	{
	}
}
