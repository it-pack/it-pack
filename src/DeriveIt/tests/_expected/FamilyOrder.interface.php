<?php

namespace _Fixture;

interface FamilyOrderInterface
{
	function id(): int;


	function lineItem(): FamilyLineItem;


	function maybeLineItem(): ?FamilyLineItem;
}
