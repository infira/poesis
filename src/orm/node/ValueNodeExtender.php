<?php

namespace Infira\Poesis\orm\node;

abstract class ValueNodeExtender
{
	protected $data       = null;
	private   $isOperator = false;
	private   $isValue    = false;
	
	public function __construct(bool $isOperator, bool $isValue)
	{
		$this->isOperator = $isOperator;
		$this->isValue    = $isValue;
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
	
	public function setData($value)
	{
		$this->data = $value;
	}
}

?>