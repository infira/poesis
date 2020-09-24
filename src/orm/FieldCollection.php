<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\orm\node\OperatorNode;
use Infira\Poesis\orm\node\GroupNode;
use Infira\Poesis\orm\node\ValueNode;
use Infira\Poesis\Poesis;

/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 */
class FieldCollection
{
	private $values = [];
	/**
	 * @var Model
	 */
	public $Orm = false;
	
	/**
	 * Sql table class helper constructor
	 */
	public function __construct(Model &$TableClass)
	{
		$this->Orm = &$TableClass;
	}
	
	
	public function __set($name, $value)
	{
		Poesis::error("#aaaaaaaa");
		$this->Orm->Schema->checkField($name);
		$this->add($name, $value, false);
	}
	
	public function __get($name)
	{
		$this->Orm->Schema->checkField($name);
		
		return $this->getField($name);
	}
	
	private function covertValueToNode($field, $value)
	{
		$Node = null;
		if (is_object($value))
		{
			if ($value instanceof ArrayListNodeVal)
			{
				$value = $value->val();
			}
			elseif ($value instanceof ValueNode)
			{
				$Node = $value;
			}
			elseif ($value instanceof OperatorNode)
			{
				$Node = $value;
			}
			elseif ($value instanceof \stdClass)
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
			Poesis::error("Cant use array ");
		}
		else
		{
			$value = ComplexValue::simpleValue($value);
		}
		
		if ($Node === null)
		{
			$Node = new ValueNode();
			$Node->set($value);
			$Node->setField($field);
			$Node->setModel($this->Orm);
			$Node->setOperator(new OperatorNode('and'));
			if (!$this->Orm->Schema->isRawField($field))
			{
				$type = $this->Orm->Schema->getType($field);
				if (in_array($type, ["enum", "set"]))
				{
					$checkValue    = $Node->get();
					$allowedValues = $this->Orm->Schema->getAllowedValues($field);
					if (!in_array($Node->getFunction(), ['force', 'notEmpty', 'empty', 'like', 'notlike', 'in', 'notIn']))
					{
						if ($this->Orm->Schema->isNullAllowed($field))
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
							$extraErrorInfo["isNullAllowed"] = $this->Orm->Schema->isNullAllowed($field);
							Poesis::error("$field value is not in allowed values", $extraErrorInfo);
						}
					}
				}
			}
		}
		
		return $Node;
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @return FieldCollection
	 */
	public function add(string $field, $value): FieldCollection
	{
		$Node = $this->covertValueToNode($field, $value);
		if ($Node->isOperator())
		{
			$this->values[array_key_last($this->values)]->setOperator($Node); //lets change last operator
		}
		else
		{
			$this->Orm->Schema->checkField($field);
			$this->values[$field][] = $Node;
		}
		
		return $this;
	}
	
	public function setGroup($field, $valueIndex, $value)
	{
		if (!$this->values[$field][$valueIndex]->isGroup())
		{
			$CollectionNode = new GroupNode();
			$CollectionNode->collect($this->values[$field][$valueIndex]);
			$this->values[$field][$valueIndex] = $CollectionNode;
		}
		$this->values[$field][$valueIndex]->collect($this->covertValueToNode($field, $value));
	}
	
	
	public function overwrite($field, $Nodes)
	{
		$this->values[$field] = $Nodes;
	}
	
	/**
	 * Add logical opeator (OR,XOR,AND) to query
	 *
	 * @param string $op - values can be or|xor|and
	 * @return FieldCollection
	 */
	public function addOperator(string $op): FieldCollection
	{
		$this->add("_OR_FIELD_", new OperatorNode($op));
		
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
	 * Get seted field
	 *
	 * @param string $field
	 * @return Field
	 */
	public function getField($field): Field
	{
		$this->Orm->Schema->checkField($field);
		
		return new Field($this, $field);
	}
	
	/**
	 * Get all field values
	 *
	 * @param string $field
	 * @return array
	 */
	public function getFieldValues($field): array
	{
		$this->Orm->Schema->checkField($field);
		if (!isset($this->values[$field]))
		{
			return [];
		}
		
		return $this->values[$field];
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
	
	/**
	 * Is field value setted
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isFieldSetted(string $field): bool
	{
		return (isset($this->values[$field]));
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
	 */
	public function nullField($field)
	{
		$this->Orm->Schema->checkField($field);
		unset($this->values[$field]);
	}
	
	/**
	 * Nulls sql field and where values
	 */
	public function nullFields($fields = false)
	{
		if ($fields === false)
		{
			$this->values = [];
		}
		else
		{
			$fields = Variable::toArray($fields);
			if (checkArray($fields))
			{
				foreach ($fields as $f)
				{
					$this->nullField($f);
				}
			}
		}
	}
	
	/**
	 * Map fields
	 *
	 * @param array|object $fields     -
	 * @param array|string $voidFields string or array of fields what to void on maping
	 * @param array        $overWrite
	 * @throws Exception
	 * @return $this
	 */
	public function map($fields, $voidFields = [], array $overWrite = [])
	{
		$fields     = array_merge(Variable::toArray($fields), Variable::toArray($overWrite));
		$voidFields = Variable::toArray($voidFields);
		if (checkArray($fields))
		{
			foreach ($fields as $f => $value)
			{
				if (!in_array($f, $voidFields) and $this->Orm->Schema->fieldExists($f))
				{
					$this->add($f, $value);
				}
			}
		}
		
		return $this;
	}
	
	public function replace(array $values)
	{
		$this->nullFields(false);
		foreach ($values as $field => $items)
		{
			$this->values[$field] = $items;
		}
	}
	
	public function set(string $field, array $values)
	{
		$this->values[$field] = $values;
	}
	
	public function setValues(array $values): FieldCollection
	{
		$this->values = $values;
		
		return $this;
	}
}

?>