<?php

namespace Infira\Poesis\orm\node;

use Infira\Utils\Variable;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Poesis\orm\Model;
use Infira\Utils\Date;
use Infira\Poesis\orm\Schema;
use Infira\Utils\Is;

class FieldNode
{
	private $name              = "";
	private $function          = "";
	private $functionArguments = [];
	
	public function __construct(string $name)
	{
		$this->name = $name;
	}
	
	
	public function setFunction(string $function, array $arguments = [])
	{
		$this->function          = $function;
		$this->functionArguments = $arguments;
	}
	
	public function getFunction(): string
	{
		return $this->function;
	}
	
	public function getFunctionArguments(): array
	{
		return $this->functionArguments;
	}
	
	
	public function hasFunction(): bool
	{
		return !empty($this->function);
	}
	
	public function getName(): string
	{
		return $this->name;
	}
	
}

?>