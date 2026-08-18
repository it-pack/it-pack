<?php

namespace _Fixture;

interface CallableAndClosureParamsClassInterface
{
	function register(callable $handler, ?\Closure $fallback = null): void;
}
