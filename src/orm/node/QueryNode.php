<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\orm\ComplexValue;

class QueryNode
{
	public $type             = '';
	public $query            = '';
	public $table            = '';
	public $selectFields     = null; //fields to use in SELECT $selectFields FROM, * - use to select all fields, otherwise it will be exploded by comma
	public $fields           = [];
	public $where            = [];
	public $orderBy          = '';
	public $groupBy          = '';
	public $limit            = '';
	public $isCollection     = false;
	public $collectionValues = [];
	public $RowParser        = null;
	
	public function hasWhere(string $name)
	{
		return $this->hasFieldInit($name, 'where');
	}
	
	public function hasField(string $name)
	{
		return $this->hasFieldInit($name, 'fields');
	}
	
	private function hasFieldInit(string $name, string $it)
	{
		foreach ($this->$it as $nodes)
		{
			foreach ($nodes as $node)
			{
				if ($node->getField() == $name)
				{
					return true;
				}
			}
		}
		
		return false;
	}
	
	
	public function addWhere(string $name, ComplexValue $value)
	{
		$this->where[][] = $value;
	}
	
	public function addField(string $name, ComplexValue $value)
	{
		$this->fields[][] = $value;
	}
}

?>