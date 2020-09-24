<?php

namespace Infira\Poesis\orm\node;
abstract class ValueNodeExtender
{
	protected $data       = null;
	private   $isGroup    = false;
	private   $isOperator = false;
	private   $isValue    = false;
	
	public function __construct($isGroup, $isOperator, $isValue)
	{
		$this->isGroup    = $isGroup;
		$this->isOperator = $isOperator;
		$this->isValue    = $isValue;
	}
	
	public final function isGroup(): bool
	{
		return $this->isGroup;
	}
	
	public final function isOperator(): bool
	{
		return $this->isOperator;
	}
	
	public final function isValue(): bool
	{
		return $this->isValue;
	}
	
	public final function get()
	{
		return $this->data;
	}
}

?>