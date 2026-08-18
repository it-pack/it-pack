<?php

namespace _Fixture;

interface TreeInterface
{
	function getRoot(): Node;


	function build(Node $seed): Node;


	function count(): int;
}
