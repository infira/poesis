<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;

class OperatorNode extends ValueNodeExtender
{
	public function __construct($op = "and")
	{
		parent::__construct(true, false);
		$this->data = $op;
	}
	
	public function set(string $op)
	{
		if ($op != "or" and $op != "and" and $op != "xor")
		{
			Poesis::error("Undefined op $op");
		}
		else
		{
			$this->data = $op;
		}
	}
}
