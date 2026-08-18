<?php

namespace _Fixture\Stubs;

interface ConflictingTraitsClassInterface
{
	function shared(): void;


	function fromFirst(): void;


	function fromSecond(): void;
}
