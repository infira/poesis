<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;

class LogicalOperator
{
	private $operator = null;
	private $column;
	
	public function __construct(string $op = "AND", string $column = '__default__locagical_operator')
	{
		if ($op != "OR" and $op != "AND" and $op != "XOR")
		{
			Poesis::error("Undefined op $op");
		}
		else
		{
			$this->operator = $op;
		}
		$this->column = $column;
	}
	
	public function get(): string
	{
		return $this->operator;
	}
	
	public function getColumn(): string
	{
		return $this->column;
	}
}
