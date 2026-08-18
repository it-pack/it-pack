<?php

namespace _Fixture;

interface GenericDocblockClassInterface
{
	/**
	 * @return T
	 */
	function get(string $key): mixed;
}
