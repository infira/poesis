<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\orm\node\OperatorNode;
use Infira\Poesis\orm\node\ValueNode;
use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\FieldNode;
use stdClass;

/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 */
class FieldCollection
{
	private $values       = [];
	private $settedFields = [];
	private $valueParser  = [];
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	public function __construct(string $schemaClassName)
	{
		$this->Schema = $schemaClassName;
	}
	
	private function valueParser($field, $value)
	{
		if (isset($this->valueParser[$field]))
		{
			$value = callback($this->valueParser[$field], null, [$value]);
		}
		
		return $value;
	}
	
	/**
	 * @param int       $groupIndex
	 * @param FieldNode $fieldNode
	 * @param mixed     $value
	 * @return FieldCollection
	 */
	public function add(int $groupIndex, FieldNode $fieldNode, $value): FieldCollection
	{
		$field = $fieldNode->getName();
		
		if (is_object($value))
		{
			if ($value instanceof ValueNode)
			{
				$Node = $value;
				$Node->setData($this->valueParser($field, $Node->get()));
			}
			elseif ($value instanceof OperatorNode)
			{
				$Node = $value;
			}
			elseif ($value instanceof stdClass)
			{
				if (!isset($value->__dbORMComplexValue))
				{
					Poesis::error("Unknwo \stdClass ", ["\stdClass" => $value]);
				}
			}
			else
			{
				Poesis::error("Cant use object = " . get_class($value));
			}
		}
		elseif (is_array($value))
		{
			Poesis::error("Cant use array");
		}
		else
		{
			$Node = ComplexValue::simpleValue($this->valueParser($field, $value));
		}
		
		
		if ($Node->isValue())
		{
			$Node->setField($field);
			$Node->setSchema($this->Schema);
			if ($Node->isType(''))
			{
				Poesis::error("NodeValue type is required", ['node' => $Node]);
			}
			
			if (!$this->Schema::isRawField($field))
			{
				$type = $this->Schema::getType($field);
				if (in_array($type, ["enum", "set"]))
				{
					$checkValue    = $Node->get();
					$allowedValues = $this->Schema::getAllowedValues($field);
					if (!$Node->isType('notEmpty,empty,like,notlike,in,notIn'))
					{
						if ($this->Schema::isNullAllowed($field))
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
							$extraErrorInfo["origValue"]     = $value;
							$extraErrorInfo["value"]         = $checkValue;
							$extraErrorInfo["allowedValues"] = $allowedValues;
							$extraErrorInfo["isNullAllowed"] = $this->Schema::isNullAllowed($field);
							Poesis::error("$field value is not in allowed values", $extraErrorInfo);
						}
					}
				}
			}
		}
		
		
		if ($fieldNode->hasFunction())
		{
			$Node->addFieldFunction($fieldNode->getFunction(), $fieldNode->getFunctionArguments());
		}
		if (!$Node->isOperator())
		{
			$this->Schema::checkField($field);
		}
		else
		{
			if (count($this->values) == 0)
			{
				Poesis::error("Cant start query with operator");
			}
		}
		$this->values[$groupIndex][] = $Node;
		$this->settedFields[$field]  = true;
		
		return $this;
	}
	
	public function setValueParser(string $field, callable $callback)
	{
		$this->valueParser[$field] = $callback;
	}
	
	/**
	 * Add logical opeator (OR,XOR,AND) to query
	 *
	 * @param int    $groupIndex
	 * @param string $op - values can be or|xor|and
	 * @return FieldCollection
	 */
	public function addOperator(int $groupIndex, string $op): FieldCollection
	{
		$this->add($groupIndex, new FieldNode("_OR_FIELD_"), new OperatorNode($op));
		
		return $this;
	}
	
	/**
	 * Delete field from values
	 *
	 * @param $field
	 * @return $this
	 */
	public function delete($field): FieldCollection
	{
		unset($this->values[$field]);
		
		return $this;
	}
	
	/**
	 * Get all fields setted values
	 *
	 * @return array
	 */
	public function getValues(): array
	{
		return $this->values;
	}
	
	public function setValues(array $values): FieldCollection
	{
		$this->values = $values;
		
		return $this;
	}
	
	/**
	 * Get all fields field names
	 *
	 * @return array
	 */
	public function getFields(): array
	{
		$fields = [];
		foreach ($this->values as $groupIndex => $values)
		{
			foreach ($values as $Node)
			{
				$fields[] = $Node->getField();
			}
		}
		
		return $fields;
	}
	
	public function hasField(string $field): bool
	{
		foreach ($this->values as $groupIndex => $values)
		{
			foreach ($values as $Node)
			{
				if ($Node->getField() == $field)
				{
					return true;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Is some fields setted
	 *
	 * @return bool
	 */
	public function hasValues()
	{
		return count($this->values) ? true : false;
	}
	
	/**
	 * Nulls sql field value
	 *
	 * @param string $field
	 */
	public function nullField(string $field)
	{
		Poesis::error("nullField not implemented");
	}
	
	/**
	 * Nulls sql field and where values
	 */
	
	/**
	 * @param mixed $fields
	 */
	public function nullFields($fields = null)
	{
		if ($fields != null)
		{
			Poesis::error("Null fields by argument is not implemented");
		}
		$this->values = [];
	}
}

?>