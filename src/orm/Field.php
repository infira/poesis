<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\OperatorNode;
use Infira\Poesis\orm\node\FieldNode;
use Infira\Utils\Date;
use Infira\Utils\Variable;

class Field
{
	/**
	 * @var Model
	 */
	private $Model;
	private $field;
	private $fieldFunction;
	private $fieldFunctionArguments = [];
	
	
	public function __construct(&$Fields, $field)
	{
		$this->Model         = &$Fields;
		$this->field         = &$field;
		$this->fieldFunction = '';
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->field as value");
	}
	
	public function add($value): Field
	{
		$fieldNode = new FieldNode($this->field);
		if ($this->fieldFunction)
		{
			$fieldNode->setFunction($this->fieldFunction, $this->fieldFunctionArguments);
		}
		$this->Model->Fields->add($this->Model->__groupIndex, $fieldNode, $value);
		
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
	
	///////////////////#################################### SOF operators
	
	/**
	 * Add logical XOR operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function xor($value = Poesis::UNDEFINED): Field
	{
		return $this->setOperator("xor", $value);
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function or($value = Poesis::UNDEFINED): Field
	{
		return $this->setOperator("or", $value);
	}
	
	/**
	 * Add logical AND operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function and($value = Poesis::UNDEFINED): Field
	{
		return $this->setOperator("and", $value);
	}
	
	/**
	 * @param string $op
	 * @param mixed  $value - add extra value to query, not required
	 * @return $this
	 */
	private function setOperator(string $op, $value = Poesis::UNDEFINED): Field
	{
		if ($value !== Poesis::UNDEFINED)
		{
			$this->add($value);
		}
		$Node = new OperatorNode($op);
		$this->add($Node);
		
		return $this;
	}
	///////////////////#################################### EOF operators
	
	///////////////////#################################### SOF Value funcions
	
	public function raw(string $query): Field
	{
		return $this->add(ComplexValue::raw($query));
	}
	
	public function in($values): Field
	{
		return $this->add(ComplexValue::in($values));
	}
	
	public function notIn($values): Field
	{
		return $this->add(ComplexValue::notIn($values));
	}
	
	public function inSubQuery($query): Field
	{
		return $this->add(ComplexValue::inSubQuery($query));
	}
	
	public function notInSubQuery($query): Field
	{
		return $this->add(ComplexValue::notInSubQuery($query));
	}
	
	public function variable(string $varName): Field
	{
		return $this->add(ComplexValue::variable($varName));
	}
	
	public function notNull(): Field
	{
		return $this->add(ComplexValue::notNull());
	}
	
	public function null(): Field
	{
		return $this->add(ComplexValue::null());
	}
	
	public function not($value): Field
	{
		return $this->add(ComplexValue::not($value));
	}
	
	public function notField(string $value): Field
	{
		return $this->add(ComplexValue::notField($value));
	}
	
	public function field(string $value): Field
	{
		return $this->add(ComplexValue::field($value));
	}
	
	public function biggerEq($value): Field
	{
		return $this->add(ComplexValue::biggerEq($value));
	}
	
	public function smallerEq($value): Field
	{
		return $this->add(ComplexValue::smallerEq($value));
	}
	
	public function bigger($value): Field
	{
		return $this->add(ComplexValue::bigger($value));
	}
	
	public function smaller($value): Field
	{
		return $this->add(ComplexValue::smaller($value));
		
	}
	
	public function md5($value, $convertFieldToMD5 = false): Field
	{
		return $this->add(ComplexValue::md5($value, $convertFieldToMD5));
	}
	
	public function compress($value): Field
	{
		return $this->add(ComplexValue::compress($value));
	}
	
	public function notEmpty(): Field
	{
		return $this->add(ComplexValue::notEmpty());
	}
	
	public function isEmpty(): Field
	{
		return $this->add(ComplexValue::isEmpty());
	}
	
	public function betweenFields(string $field1, string $field2): Field
	{
		return $this->add(ComplexValue::betweenFields($field1, $field2));
	}
	
	public function notBetweenFields(string $field1, string $field2): Field
	{
		return $this->add(ComplexValue::notBetweenFields($field1, $field2));
	}
	
	public function between($value1, $value2): Field
	{
		return $this->add(ComplexValue::between($value1, $value2));
	}
	
	public function notBetween($value1, $value2): Field
	{
		return $this->add(ComplexValue::notBetween($value1, $value2));
	}
	
	public function like($value): Field
	{
		return $this->add(ComplexValue::like($value));
	}
	
	public function likeP($value): Field
	{
		return $this->add(ComplexValue::likeP($value));
	}
	
	public function notLike($value): Field
	{
		return $this->add(ComplexValue::notLike($value));
	}
	
	public function notLikeP($value): Field
	{
		return $this->add(ComplexValue::notLikeP($value));
	}
	
	public function now(): Field
	{
		return $this->add(ComplexValue::now());
	}
	
	/**
	 * @param string $function
	 * @param array  $arguments - must contain %field% item to determine argument order
	 * @return $this
	 */
	public function sqlFunction(string $function, $arguments = []): Field
	{
		if (!in_array('%field%', $arguments))
		{
			//Poesis::error("SQL function $function must contain value %field%");
		}
		$this->fieldFunction          = $function;
		$this->fieldFunctionArguments = $arguments;
		
		return $this;
	}
	
	///////////////////#################################### EOF Value funcions
	///////////////////#################################### EOF Converters
	
	public function json($value): Field
	{
		return $this->add(json_encode($value));
	}
	
	public function date($date): Field
	{
		return $this->add(Date::toSqlDate($date));
	}
	
	
	public function dateTime($dateTime): Field
	{
		return $this->add(Date::toSqlDateTime($dateTime));
	}
	
	public function timestamp($dateTime): Field
	{
		return $this->add(Date::toTime($dateTime));
	}
	
	public function int($value = 0): Field
	{
		return $this->add(intval($value));
	}
	
	public function round($value): Field
	{
		return $this->add($this->Model->Schema::round($this->field, $value));
	}
	
	///////////////////#################################### SOF Converters
	
	#######################################################################################################################
	
	
	public function increase($by): Field
	{
		return $this->add(ComplexValue::increase($by));
	}
	
	public function decrease($by): Field
	{
		return $this->add(ComplexValue::decrease($by));
	}
}

?>