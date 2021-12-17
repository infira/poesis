<?php

namespace Infira\Poesis;

class Error extends \Exception
{
	private $_data = [];
	
	public function __construct($message, array $data = null)
	{
		if ($data)
		{
			$this->_data = $data;
		}
		parent::__construct($message);
	}
	
	public function getData(): array
	{
		return $this->_data;
	}
}