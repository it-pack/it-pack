<?php

namespace _Fixture;

interface FinalMethodsInNonFinalClassInterface
{
	function cannotOverride(): void;


	function canOverride(): void;
}
