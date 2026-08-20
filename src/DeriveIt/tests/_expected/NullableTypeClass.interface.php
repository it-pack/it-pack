<?php

namespace _Fixture;

interface NullableTypeClassInterface
{
	function find(?string $id = null): ?NullableTypeClassInterface;
}
