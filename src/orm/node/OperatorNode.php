<?php

namespace Infira\Poesis\orm\node;

class OperatorNode extends ValueNodeExtender
{
	public function __construct($op = "and")
	{
		parent::__construct(false, true, false);
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
