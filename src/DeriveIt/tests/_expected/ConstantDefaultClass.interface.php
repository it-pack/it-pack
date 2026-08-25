<?php

namespace _Fixture;

interface ConstantDefaultClassInterface
{
	function withDefault(string $value = _Fixture\ConstantDefaultClassInterface::DEFAULT): void;
}
