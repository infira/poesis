<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;

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
	
	private function valueParser(string $column, $value)
	{
		if (isset($this->valueParser[$column]))
		{
			$value = callback($this->valueParser[$column], null, [$value]);
		}
		
		return $value;
	}
	
	/**
	 * @param int   $groupIndex
	 * @param Field $field
	 * @return Clause
	 */
	public function add(int $groupIndex, Field $field): Clause
	{
		$columnName = $field->getColumn();
		$field->setConnectionName($this->connectionName);
		$field->setSchema($this->Schema);
		if ($field->isPredicateType(''))
		{
			Poesis::error("NodeValue type is required", ['node' => $field]);
		}
		
		$type = $this->Schema::getType($columnName);
		if (isset($this->valueParser[$columnName]))
		{
			$field->setValue(callback($this->valueParser[$columnName], null, [$field->getValue()]));
		}
		if (in_array($type, ["enum", "set"]))
		{
			$checkValue    = $field->getValue();
			$allowedValues = $this->Schema::getAllowedValues($columnName);
			if (!$field->isPredicateType('notEmpty,empty,like,notlike,in,notIn'))
			{
				if ($this->Schema::isNullAllowed($columnName))
				{
					if ($checkValue === "null")
					{
						$checkValue = null;
					}
					$allowedValues[] = null;
				}
				if (empty($checkValue) and $type == "set")
				{
					$allowedValues[] = "";
				}
				if (!in_array($checkValue, $allowedValues, true))
				{
					Poesis::clearErrorExtraInfo();
					$extraErrorInfo                  = [];
					$extraErrorInfo["valueType"]     = gettype($checkValue);
					$extraErrorInfo["value"]         = $checkValue;
					$extraErrorInfo["allowedValues"] = $allowedValues;
					$extraErrorInfo["isNullAllowed"] = $this->Schema::isNullAllowed($columnName);
					Poesis::error("$columnName value is not in allowed values", $extraErrorInfo);
				}
			}
		}
		
		$this->Schema::checkColumn($columnName);
		$this->values[$groupIndex][]      = $field;
		$this->settedColimns[$columnName] = true;
		
		return $this;
	}
	
	/**
	 * Adds a value parset what is called just before add value to collection
	 * $callback($value)
	 *
	 * @param string   $column
	 * @param callable $callback
	 */
	public function setValueParser(string $column, callable $callback)
	{
		$this->valueParser[$column] = $callback;
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
	
	public function checkForErrors()
	{
		$addedFields = [];
		foreach ($this->values as $groupIndex => $groupItems)
		{
			/**
			 * @var Field $Field
			 */
			foreach ($groupItems as $Field)
			{
				$field = $Field->getColumn();
				if (isset($addedFields[$field]))
				{
					Poesis::error("ModelColumn $field specified twice");
				}
				$addedFields[$field] = true;
			}
		}
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
	
	/**
	 * Is some columns setted
	 *
	 * @return bool
	 */
	public function hasValues()
	{
		return count($this->values) ? true : false;
	}
	
	public function flush()
	{
		$this->values = [];
	}
}

?>