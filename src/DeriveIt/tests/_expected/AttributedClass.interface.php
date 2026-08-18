<?php

namespace _Fixture;

interface AttributedClassInterface
{
	#[\Deprecated(since: '2.0')]
	function handle(
		#[\SensitiveParameter]
		$input,
	): void;
}
