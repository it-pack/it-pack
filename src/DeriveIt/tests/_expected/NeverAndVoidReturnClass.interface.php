<?php

namespace _Fixture;

interface NeverAndVoidReturnClassInterface
{
	function alwaysThrows(): never;


	function noReturnValue(): void;
}
