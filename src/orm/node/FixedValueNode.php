<?php

namespace Infira\Poesis\orm\node;

use Infira\Utils\Is;
use Infira\Poesis\Poesis;

class FixedValueNode
{
	private $type  = '';
	private $value = '';
	
	public function value($value = null)
	{
		if ($value === null)
		{
			return $this->value;
		}
		$this->value = $value;
	}
	
	public function type(string $type = null)
	{
		if ($type === null)
		{
			return $this->type;
		}
		$this->type = $type;
	}
	
	public function detectType()
	{
		if (Is::number($this->value))
		{
			$this->type = 'numeric';
		}
		else
		{
			$this->type = 'string';
		}
	}
	
	public function get()
	{
		$type  = $this->type();
		$value = $this->value();
		if (!is_string($value) and !is_numeric($value))
		{
			Poesis::error('value must string or number', ['value' => $value]);
		}
		
		if (in_array($type, ['expression', 'function', 'numeric']))
		{
			return $value;
		}
		elseif ($type == 'string')
		{
			return $this->quoted();
		}
		else
		{
			Poesis::error('Unknown type', ['type' => $type]);
		}
	}
	
	public function quoted()
	{
		return "'" . $this->value . "'";
	}
	
}

?>