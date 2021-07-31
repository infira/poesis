<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\LogicalOperator;
use Infira\Poesis\orm\node\Field;

class ModelColumn
{
	/**
	 * @var Model
	 */
	private $Model;
	private $column;
	private $columnFunctions = [];
	private $valueFunctions  = [];
	private $logicalOperator = null;
	
	
	public function __construct(&$model, $name)
	{
		$this->Model           = &$model;
		$this->column          = &$name;
		$this->columnFunctions = [];
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->column as value");
	}
	
	protected function add(Field $field): ModelColumn
	{
		$field->setColumn($this->column);
		foreach ($this->columnFunctions as $f)
		{
			$field->addColumnFunction($f[0], $f[1]);
		}
		foreach ($this->valueFunctions as $f)
		{
			$field->addValueFunction($f[0], $f[1]);
		}
		
		$this->columnFunctions = [];
		if ($this->logicalOperator === '!=')
		{
			if ($field->isPredicateType('between,like,in'))
			{
				$field->setLogicalOperator('NOT ' . strtoupper($field->getPredicateType()));
			}
			elseif ($field->isPredicateType('null'))
			{
				$field->setLogicalOperator('NOT');
			}
			else
			{
				$field->setLogicalOperator('!=');
			}
		}
		elseif ($this->logicalOperator !== null)
		{
			$field->setLogicalOperator($this->logicalOperator);
		}
		$this->Model->__clause()->add($this->Model->__groupIndex, $field);
		
		return $this;
	}
	
	public final function __call($method, $arguments)
	{
		if (in_array($method, ['select']))
		{
			return $this->Model->$method(...$arguments);
		}
		Poesis::error('You are tring to call uncallable method <B>"' . $method . '</B>" it doesn\'t exits in ' . get_class($this) . ' class');
	}
	
	//region operators
	
	/**
	 * Add logical XOR operator to query
	 *
	 * @return $this
	 */
	public function xor(): ModelColumn
	{
		return $this->addOperator("XOR");
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @return $this
	 */
	public function or(): ModelColumn
	{
		return $this->addOperator("OR");
	}
	
	/**
	 * Add logical AND operator to query
	 *
	 * @return $this
	 */
	public function and(): ModelColumn
	{
		return $this->addOperator("AND");
	}
	
	/**
	 * @param string $op
	 * @return $this
	 */
	private function addOperator(string $op): ModelColumn
	{
		$this->Model->__clause()->addOperator($this->Model->__groupIndex, new LogicalOperator($op, $this->column));
		
		return $this;
	}
	
	/**
	 * Set logical operator
	 *
	 * @param string $op
	 * @return $this
	 */
	public function lop(string $op): ModelColumn
	{
		$this->logicalOperator = $op;
		
		return $this;
	}
	
	/**
	 * Set locical operator
	 *
	 * @param mixed $value - is IS NOT Poesis::UNDEFINED then self->notValue is used
	 * @return $this
	 */
	public function not($value = Poesis::UNDEFINED): ModelColumn
	{
		$this->lop('!=');
		if ($value !== Poesis::UNDEFINED)
		{
			return $this->notValue($value);
		}
		
		return $this;
	}
	
	//endregion
	
	public function value($value): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue($value));
	}
	
	public function notValue($value): ModelColumn
	{
		return $this->add(ComplexValue::not($value));
	}
	
	//region raw values
	public function raw(string $rawValue): ModelColumn
	{
		return $this->add(ComplexValue::raw($rawValue));
	}
	
	public function query(string $query): ModelColumn
	{
		return $this->add(ComplexValue::query($query));
	}
	
	public function variable(string $varName): ModelColumn
	{
		return $this->add(ComplexValue::variable($varName));
	}
	
	public function null(): ModelColumn
	{
		return $this->add(ComplexValue::null());
	}
	
	public function column(string $column): ModelColumn
	{
		return $this->add(ComplexValue::column($column));
	}
	
	public function now($logicalOperator = '='): ModelColumn
	{
		return $this->add(ComplexValue::now($logicalOperator));
	}
	//endregion
	
	//region select,delete complex value EDIT IS NOT ALLOWED
	
	public function notNull(): ModelColumn
	{
		return $this->add(ComplexValue::notNull());
	}
	
	public function notColumn(string $column): ModelColumn
	{
		return $this->add(ComplexValue::notColumn($column));
	}
	
	public function in($values): ModelColumn
	{
		return $this->add(ComplexValue::in($values));
	}
	
	public function notIn($values): ModelColumn
	{
		return $this->add(ComplexValue::notIn($values));
	}
	
	public function inSubQuery(string $query): ModelColumn
	{
		return $this->add(ComplexValue::inSubQuery($query));
	}
	
	public function notInSubQuery(string $query): ModelColumn
	{
		return $this->add(ComplexValue::notInSubQuery($query));
	}
	
	public function biggerEq($value): ModelColumn
	{
		return $this->add(ComplexValue::biggerEq($value));
	}
	
	public function smallerEq($value): ModelColumn
	{
		return $this->add(ComplexValue::smallerEq($value));
	}
	
	public function bigger($value): ModelColumn
	{
		return $this->add(ComplexValue::bigger($value));
	}
	
	public function smaller($value): ModelColumn
	{
		return $this->add(ComplexValue::smaller($value));
		
	}
	
	public function notEmpty(): ModelColumn
	{
		return $this->add(ComplexValue::notEmpty());
	}
	
	public function empty(): ModelColumn
	{
		return $this->add(ComplexValue::empty());
	}
	
	public function between($value1, $value2): ModelColumn
	{
		return $this->add(ComplexValue::between($value1, $value2));
	}
	
	public function notBetween($value1, $value2): ModelColumn
	{
		return $this->add(ComplexValue::notBetween($value1, $value2));
	}
	
	public function betweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(ComplexValue::betweenColumns($column1, $column2));
	}
	
	public function notBetweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(ComplexValue::notBetweenColumns($column1, $column2));
	}
	
	public function like($value): ModelColumn
	{
		return $this->add(ComplexValue::like($value));
	}
	
	public function likeP($value): ModelColumn
	{
		return $this->add(ComplexValue::likeP($value));
	}
	
	public function notLike($value): ModelColumn
	{
		return $this->add(ComplexValue::notLike($value));
	}
	
	public function notLikeP($value): ModelColumn
	{
		return $this->add(ComplexValue::notLikeP($value));
	}
	
	public function rlike($value): ModelColumn
	{
		return $this->add(ComplexValue::rlike($value));
	}
	
	public function notRlike($value): ModelColumn
	{
		return $this->add(ComplexValue::rlike($value));
	}
	//endregion
	
	//region value modifiers
	public function md5($value): ModelColumn
	{
		return $this->add(ComplexValue::md5($value));
	}
	
	public function compress($value): ModelColumn
	{
		return $this->add(ComplexValue::compress($value));
	}
	
	public function increase($by): ModelColumn
	{
		return $this->add(ComplexValue::increase($by));
	}
	
	public function decrease($by): ModelColumn
	{
		return $this->add(ComplexValue::decrease($by));
	}
	
	public function json($value): ModelColumn
	{
		return $this->add(ComplexValue::json($value));
	}
	
	public function serialize($value): ModelColumn
	{
		return $this->add(ComplexValue::serialize($value));
	}
	
	public function time($time): ModelColumn
	{
		return $this->add(ComplexValue::time($time));
	}
	
	public function date($date): ModelColumn
	{
		return $this->add(ComplexValue::date($date));
	}
	
	public function dateTime($dateTime): ModelColumn
	{
		return $this->add(ComplexValue::dateTime($dateTime));
	}
	
	public function timestamp($timestamp): ModelColumn
	{
		return $this->add(ComplexValue::timestamp($timestamp));
	}
	
	public function int($value = 0): ModelColumn
	{
		return $this->add(ComplexValue::int($value));
	}
	
	public function float($value = 0): ModelColumn
	{
		return $this->add(ComplexValue::float($value));
	}
	
	/**
	 * Trim value before seting
	 *
	 * @param string $value
	 * @return $this
	 */
	public function trim($value): ModelColumn
	{
		return $this->value(trim($value));
	}
	
	public function boolInt($value): ModelColumn
	{
		return $this->add(ComplexValue::boolInt($value));
	}
	
	/**
	 * Round value to column specified decimal points
	 *
	 * @param $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function round($value): ModelColumn
	{
		return $this->value($this->Model->Schema::round($this->column, $value));
	}
	
	/**
	 * Cut value to column specified length
	 *
	 * @param $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function substr($value): ModelColumn
	{
		return $this->value(substr($value, 0, $this->Model->Schema::getLength($this->column)));
	}
	
	/**
	 * Will fix value according to db column type
	 *
	 * @param mixed $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function auto($value): ModelColumn
	{
		$type = $this->Model->Schema::getType($this->column);
		if (preg_match('/int/i', $type))
		{
			return $this->int($value);
		}
		elseif (in_array($type, ['float', 'double', 'real', 'decimal']))
		{
			return $this->float($value);
		}
		elseif (preg_match('/datetime/i', $type))
		{
			return $this->dateTime($value);
		}
		elseif (preg_match('/timestamp/i', $type))
		{
			return $this->timestamp($value);
		}
		elseif (preg_match('/date/i', $type))
		{
			return $this->date($value);
		}
		elseif (preg_match('/time/i', $type))
		{
			return $this->time($value);
		}
		
		return $this->value($value);
	}
	//endregion
	
	
	/**
	 * Add SQL function to column
	 *
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function columnFunction(string $function, array $arguments = []): ModelColumn
	{
		$this->columnFunctions[] = [$function, $arguments];
		
		return $this;
	}
	
	/**
	 * Shortut for columnFunction
	 *
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function colf(string $function, array $arguments = []): ModelColumn
	{
		return $this->columnFunction($function, $arguments);
	}
	
	/**
	 * Add SQL function to value
	 *
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function valueFunction(string $function, array $arguments = []): ModelColumn
	{
		$this->valueFunctions[] = [$function, $arguments];
		
		return $this;
	}
	
	/**
	 * Shortut for valueFunction
	 *
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function volf(string $function, array $arguments = []): ModelColumn
	{
		return $this->valueFunction($function, $arguments);
	}
}

?>