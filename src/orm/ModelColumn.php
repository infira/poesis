<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\LogicalOperator;
use Infira\Utils\Date;
use Infira\Poesis\orm\node\Field;
use Infira\Utils\Variable;

class ModelColumn
{
	/**
	 * @var Model
	 */
	private $Model;
	private $column;
	private $columnFunction;
	private $columnFunctionArguments = [];
	
	
	public function __construct(&$model, $name)
	{
		$this->Model          = &$model;
		$this->column         = &$name;
		$this->columnFunction = '';
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->column as value");
	}
	
	private function add(Field $field): ModelColumn
	{
		$field->setColumn($this->column);
		if ($this->columnFunction)
		{
			$field->addColumnsFunction($this->columnFunction, $this->columnFunctionArguments);
			$this->columnFunction          = '';
			$this->columnFunctionArguments = [];
		}
		$this->Model->Clause->add($this->Model->__groupIndex, $field);
		
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
	
	//region ######################################### oerators
	
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
		$this->Model->Clause->addOperator($this->Model->__groupIndex, new LogicalOperator($op, $this->column));
		
		return $this;
	}
	//endregion ######################################### oerators
	
	///////////////////#################################### SOF Value funcions
	
	public function raw(string $rawValue): ModelColumn
	{
		return $this->add(ComplexValue::raw($rawValue));
	}
	
	public function query(string $query): ModelColumn
	{
		return $this->add(ComplexValue::query($query));
	}
	
	public function in($values): ModelColumn
	{
		return $this->add(ComplexValue::in($values));
	}
	
	public function notIn($values): ModelColumn
	{
		return $this->add(ComplexValue::notIn($values));
	}
	
	public function inSubQuery($query): ModelColumn
	{
		return $this->add(ComplexValue::inSubQuery($query));
	}
	
	public function notInSubQuery($query): ModelColumn
	{
		return $this->add(ComplexValue::notInSubQuery($query));
	}
	
	public function variable(string $varName): ModelColumn
	{
		return $this->add(ComplexValue::variable($varName));
	}
	
	public function notNull(): ModelColumn
	{
		return $this->add(ComplexValue::notNull());
	}
	
	public function null(): ModelColumn
	{
		return $this->add(ComplexValue::null());
	}
	
	public function not($value): ModelColumn
	{
		return $this->add(ComplexValue::not($value));
	}
	
	public function notColumn(string $column): ModelColumn
	{
		return $this->add(ComplexValue::notColumn($column));
	}
	
	public function column(string $column): ModelColumn
	{
		return $this->add(ComplexValue::column($column));
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
	
	public function md5($value, $convertColumnToMD5 = false): ModelColumn
	{
		return $this->add(ComplexValue::md5($value, $convertColumnToMD5));
	}
	
	public function compress($value): ModelColumn
	{
		return $this->add(ComplexValue::compress($value));
	}
	
	public function notEmpty(): ModelColumn
	{
		return $this->add(ComplexValue::notEmpty());
	}
	
	public function isEmpty(): ModelColumn
	{
		return $this->add(ComplexValue::isEmpty());
	}
	
	public function betweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(ComplexValue::betweenColumns($column1, $column2));
	}
	
	public function notBetweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(ComplexValue::notBetweenColumns($column1, $column2));
	}
	
	public function between($value1, $value2): ModelColumn
	{
		return $this->add(ComplexValue::between($value1, $value2));
	}
	
	public function notBetween($value1, $value2): ModelColumn
	{
		return $this->add(ComplexValue::notBetween($value1, $value2));
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
	
	public function now($logicalOperator = '='): ModelColumn
	{
		return $this->add(ComplexValue::now($logicalOperator));
	}
	
	/**
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function sqlFunction(string $function, $arguments = []): ModelColumn
	{
		$this->columnFunction          = $function;
		$this->columnFunctionArguments = $arguments;
		
		return $this;
	}
	
	///////////////////#################################### EOF Value funcions
	///////////////////#################################### EOF Converters
	
	public function json($value): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(json_encode($value)));
	}
	
	public function serialize($value): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(serialize($value)));
	}
	
	public function date($date): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(Date::toSqlDate($date)));
	}
	
	
	public function dateTime($dateTime): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(Date::toSqlDateTime($dateTime)));
	}
	
	public function timestamp($dateTime): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(Date::toTime($dateTime)));
	}
	
	public function int($value = 0): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue(intval($value)));
	}
	
	public function boolInt($value): ModelColumn
	{
		$int = (Variable::toBool($value, true)) ? 1 : 0;
		
		return $this->int($int);
	}
	
	public function round($value): ModelColumn
	{
		return $this->add(ComplexValue::simpleValue($this->Model->Schema::round($this->column, $value)));
	}
	
	///////////////////#################################### SOF Converters
	
	#######################################################################################################################
	
	
	public function increase($by): ModelColumn
	{
		return $this->add(ComplexValue::increase($by));
	}
	
	public function decrease($by): ModelColumn
	{
		return $this->add(ComplexValue::decrease($by));
	}
}

?>