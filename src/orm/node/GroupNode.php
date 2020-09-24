<?php

namespace Infira\Poesis\orm\node;
class GroupNode extends ValueNodeExtender
{
	public function __construct()
	{
		parent::__construct(true, false, false);
		$this->data = [];
	}
	
	
	public function collect(object $Node)
	{
		if ($Node->isOperator())
		{
			$this->data[array_key_last($this->data)]->setOperator($Node); //Change the operator
		}
		else
		{
			$this->data[] = $Node;
		}
		
	}
	
	public function ok()
	{
		return checkArray($this->data);
	}
}
