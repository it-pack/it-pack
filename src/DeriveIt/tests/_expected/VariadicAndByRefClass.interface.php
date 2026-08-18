<?php

namespace _Fixture;

interface VariadicAndByRefClassInterface
{
	function combine(string ...$parts): array;


	function increment(int &$counter): void;
}
