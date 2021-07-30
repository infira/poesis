<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\Schema;

class Clause
{
	private $values        = [];
	private $settedColimns = [];
	private $valueParser   = [];
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	private $connectionName;
	
	/**
	 * Clause constructor.
	 *
	 * @param string $schemaClassName
	 * @param string $connectionName - name for ConnectionManager instance
	 */
	public function __construct(string $schemaClassName, string $connectionName)
	{
		$this->Schema         = $schemaClassName;
		$this->connectionName = $connectionName;
	}
	
	/**
	 * @param int   $groupIndex
	 * @param Field $field
	 * @return Clause
	 */
	public function add(int $groupIndex, Field $field): Clause
	{
		$columnName = $field->getColumn();
		$this->Schema::checkColumn($columnName);
		$field->setConnectionName($this->connectionName);
		$field->setSchema($this->Schema);
		
		if (isset($this->valueParser[$columnName]))
		{
			$value = $field->getValue();
			foreach ($this->valueParser[$columnName] as $parser)
			{
				$value = call_user_func_array($parser->parser, array_merge([$value], $parser->arguments));
			}
			$field->setValue($value);
		}
		$field->validate();
		
		$this->values[$groupIndex][]      = $field;
		$this->settedColimns[$columnName] = true;
		
		return $this;
	}
	
	/**
	 * Adds a value parset what is called just before add value to collection
	 * $callback($value)
	 *
	 * @param string   $column
	 * @param callable $parser
	 * @param array    $arguments
	 */
	public function addValueParser(string $column, callable $parser, array $arguments = [])
	{
		$this->valueParser[$column][] = (object)['parser' => $parser, 'arguments' => $arguments];
	}
	
	/**
	 * Add logical opeator (OR,XOR,AND) to query
	 *
	 * @param int             $groupIndex
	 * @param LogicalOperator $op - values can be or|xor|and
	 * @return Clause
	 */
	public function addOperator(int $groupIndex, LogicalOperator $op): Clause
	{
		if (count($this->values) == 0)
		{
			Poesis::error("Cant start query with logical operator");
		}
		$this->values[$groupIndex][]           = $op;
		$this->settedColimns[$op->getColumn()] = true;
		
		return $this;
	}
	
	/**
	 * Delete column from values
	 *
	 * @param $column
	 * @return $this
	 */
	public function delete($column): Clause
	{
		unset($this->values[$column]);
		
		return $this;
	}
	
	/**
	 * Get all columns setted values
	 *
	 * @return array
	 */
	public function getValues(): array
	{
		return $this->values;
	}
	
	public function setValues(array $values): Clause
	{
		$this->values = $values;
		
		return $this;
	}
	
	/**
	 * Get all column names
	 *
	 * @return array
	 */
	public function getColumns(): array
	{
		$getColumns = [];
		foreach ($this->values as $groupIndex => $values)
		{
			foreach ($values as $Node)
			{
				$getColumns[] = $Node->getColumn();
			}
		}
		
		return $getColumns;
	}
	
	public function hasColumn(string $column): bool
	{
		foreach ($this->values as $groupIndex => $values)
		{
			foreach ($values as $Node)
			{
				if ($Node->getColumn() == $column)
				{
					return true;
				}
			}
		}
		
		return false;
	}
	
	public function getValue(string $column)
	{
		foreach ($this->values as $groupIndex => $values)
		{
			foreach ($values as $Node)
			{
				if ($Node->getColumn() == $column)
				{
					return $Node->getValue();
				}
			}
		}
		
		return null;
	}
	
	/**
	 * Is some columns setted
	 *
	 * @return bool
	 */
	public function hasValues(): bool
	{
		return (bool)count($this->values);
	}
	
	public function flush()
	{
		$this->values = [];
	}
}

?>