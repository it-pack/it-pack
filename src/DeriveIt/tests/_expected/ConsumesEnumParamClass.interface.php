<?php

namespace _Fixture;

interface ConsumesEnumParamClassInterface
{
	function withDistribution(\SimpleBackedEnum $distribution = SimpleBackedEnum::DEFAULT): static;
}
