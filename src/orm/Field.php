<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\OperatorNode;
use Infira\Utils\Date;

class Field
{
	/**
	 * @var Model
	 */
	private $Model;
	private $field;
	
	public function __construct(&$Fields, $field)
	{
		$this->Model = &$Fields;
		$this->field = &$field;
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->field as value");
	}
	
	public function add($value)
	{
		$this->Model->add($this->field, $value);
		
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
	
	/**
	 * @param mixed $values
	 * @return $this
	 */
	public function in($values)
	{
		return $this->add(ComplexValue::in($values));
	}
	
	/**
	 * @param mixed $values
	 * @return $this
	 */
	public function notIn($values)
	{
		return $this->add(ComplexValue::notIn($values));
	}
	
	/**
	 * @param mixed $query
	 * @return $this
	 */
	public function inSubQuery($query)
	{
		return $this->add(ComplexValue::inSubQuery($query));
	}
	
	/**
	 * @param mixed $query
	 * @return $this
	 */
	public function notInSubQuery($query)
	{
		return $this->add(ComplexValue::notInSubQuery($query));
	}
	
	/**
	 * Add Raw sql query
	 *
	 * @param string|object $query
	 * @return $this
	 */
	public function raw($query)
	{
		return $this->add(ComplexValue::query($query));
	}
	
	/**
	 * Just force set value
	 *
	 * @param mixed $value
	 * @return $this
	 */
	public function force($value)
	{
		return $this->add(ComplexValue::force($value));
	}
	
	/**
	 * @param float $by
	 * @return $this
	 */
	public function increase($by)
	{
		return $this->add(ComplexValue::increase($by));
	}
	
	/**
	 * @param float $by
	 * @return $this
	 */
	public function decrease($by)
	{
		return $this->add(ComplexValue::decrease($by));
	}
	
	/**
	 * @param mixed $varName
	 * @return $this
	 */
	public function variable($varName)
	{
		return $this->add(ComplexValue::variable($varName));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function not($value)
	{
		return $this->add(ComplexValue::not($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function notField($value)
	{
		return $this->add(ComplexValue::notField($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function field($value)
	{
		return $this->add(ComplexValue::field($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function biggerEq($value)
	{
		return $this->add(ComplexValue::biggerEq($value));
		
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function smallerEq($value)
	{
		return $this->add(ComplexValue::smallerEq($value));
		
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function bigger($value)
	{
		return $this->add(ComplexValue::bigger($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function smaller($value)
	{
		return $this->add(ComplexValue::smaller($value));
		
	}
	
	/**
	 * Add logical XOR operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function xor($value = Poesis::UNDEFINED)
	{
		return $this->setOperator("xor", $value);
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function or($value = Poesis::UNDEFINED)
	{
		return $this->setOperator("or", $value);
	}
	
	/**
	 * Add logical AND operator to query
	 *
	 * @param mixed $value - add extra value to query, not required
	 * @return $this
	 */
	public function and($value = Poesis::UNDEFINED)
	{
		return $this->setOperator("and", $value);
	}
	
	/**
	 * @param string $op
	 * @param mixed  $value - add extra value to query, not required
	 * @return $this
	 */
	private function setOperator(string $op, $value = Poesis::UNDEFINED)
	{
		if ($value !== Poesis::UNDEFINED)
		{
			$this->add($value);
		}
		$Node = new OperatorNode($op);
		$this->add($Node);
		
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function notEmpty()
	{
		return $this->add(ComplexValue::notEmpty());
	}
	
	/**
	 * @return $this
	 */
	public function isEmpty()
	{
		return $this->add(ComplexValue::isEmpty());
	}
	
	/**
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return $this
	 */
	public function between($value1, $value2)
	{
		return $this->add(ComplexValue::between($value1, $value2));
	}
	
	/**
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return $this
	 */
	public function betweenDateTime($date)
	{
		$from = Date::toSqlDateTime($date . " 00:00:00");
		$to   = Date::toSqlDateTime($date . " 23:59:59");
		
		return $this->add(ComplexValue::between($from, $to));
	}
	
	/**
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return $this
	 */
	public function notBetween($value1, $value2)
	{
		return $this->add(ComplexValue::notBetween($value1, $value2));
	}
	
	/**
	 * COnvert value to bool int 1 for success , 0 for null,false, empty and so
	 *
	 * @param mixed $value
	 * @return $this
	 */
	public function boolInt($value)
	{
		$int = (Variable::toBool($value, true)) ? 1 : 0;
		$this->add($int);
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function like($value, bool $fieldLower)
	{
		return $this->add(ComplexValue::like($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function likeP($value, bool $fieldLower)
	{
		return $this->add(ComplexValue::likeP($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function notLike($value, bool $fieldLower)
	{
		return $this->add(ComplexValue::notLike($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function notLikeP($value, bool $fieldLower)
	{
		return $this->add(ComplexValue::notLikeP($value, $fieldLower));
	}
	
	/**
	 * @param      $value
	 * @param bool $fieldLower - check field as lower
	 * @return $this
	 */
	public function string($value, bool $fieldLower = false)
	{
		return $this->add(ComplexValue::string($value, $fieldLower));
	}
	
	/**
	 * @return $this
	 */
	public function now()
	{
		return $this->add(ComplexValue::now());
	}
	
	/**
	 * Set field date
	 *
	 * @return $this
	 */
	public function date($date)
	{
		return $this->add(Date::toSqlDate($date));
	}
	
	/**
	 * Set field date
	 *
	 * @return $this
	 */
	public function dateField($date)
	{
		return $this->add(ComplexValue::dateField($date));
	}
	
	/**
	 * Round self value to field length determined in schema
	 *
	 * @return $this
	 */
	public function round($value)
	{
		return $this->add($this->Model->Schema::round($this->field, $value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function md5($value)
	{
		return $this->add(md5($value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function json($value)
	{
		return $this->add(json_encode($value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function compress($value)
	{
		return $this->add(ComplexValue::compress($value));
	}
	
	/**
	 * Set field value in md5 and check againts MD5(field)
	 *
	 * @return $this
	 */
	public function md5Field($value)
	{
		return $this->add(ComplexValue::md5Field($value));
	}
	
	/**
	 * Set field date time
	 *
	 * @return $this
	 */
	public function dateTime($dateTime)
	{
		return $this->add(Date::toSqlDateTime($dateTime));
	}
	
	/**
	 * Set field date time
	 *
	 * @return $this
	 */
	public function timestamp($dateTime)
	{
		return $this->add(Date::toTime($dateTime));
	}
	
	/**
	 * @return $this
	 */
	public function null()
	{
		return $this->add(ComplexValue::null());
	}
	
	/**
	 * @return $this
	 */
	public function int($var = 0)
	{
		return $this->add(intval($var));
	}
	
	/**
	 * @return $this
	 */
	public function notNull()
	{
		return $this->add(ComplexValue::notNull());
	}
}

?>