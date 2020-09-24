<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\OperatorNode;
use Infira\Utils\Date;

class Field
{
	/**
	 * @var FieldCollection
	 */
	private $Fields;
	
	private $field;
	private $setValuesToGroup = null;
	
	public function __construct(&$Parent, $field)
	{
		$this->Fields = &$Parent;
		$this->field  = &$field;
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->field as value");
	}
	
	/**
	 * Set value
	 *
	 * @param $value
	 * @return $this
	 */
	public function set($value)
	{
		if ($this->setValuesToGroup !== null)
		{
			$this->Fields->setGroup($this->field, $this->setValuesToGroup, $value);
		}
		else
		{
			$this->Fields->add($this->field, $value);
		}
		
		return $this->cloneThis();
	}
	
	public final function __call($method, $arguments)
	{
		if (in_array($method, ['select']))
		{
			return $this->Fields->Orm->$method(...$arguments);
		}
		Poesis::error('You are tring to call uncallable method <B>"' . $method . '</B>" it doesn\'t exits in ' . get_class($this) . ' class');
	}
	
	/**
	 * @param mixed $values
	 * @return $this
	 */
	public function in($values)
	{
		return $this->set(ComplexValue::in($values));
	}
	
	/**
	 * @param mixed $values
	 * @return $this
	 */
	public function notIn($values)
	{
		return $this->set(ComplexValue::notIn($values));
	}
	
	/**
	 * @param mixed $query
	 * @return $this
	 */
	public function inSubQuery($query)
	{
		return $this->set(ComplexValue::inSubQuery($query));
	}
	
	/**
	 * @param mixed $query
	 * @return $this
	 */
	public function notInSubQuery($query)
	{
		return $this->set(ComplexValue::notInSubQuery($query));
	}
	
	/**
	 * Add Raw sql query
	 *
	 * @param string|object $query
	 * @return $this
	 */
	public function raw($query)
	{
		return $this->set(ComplexValue::query($query));
	}
	
	/**
	 * Just force set value
	 *
	 * @param mixed $value
	 * @return $this
	 */
	public function force($value)
	{
		return $this->set(ComplexValue::force($value));
	}
	
	/**
	 * @param float $by
	 * @return $this
	 */
	public function increase($by)
	{
		return $this->set(ComplexValue::increase($by));
	}
	
	/**
	 * @param float $by
	 * @return $this
	 */
	public function decrease($by)
	{
		return $this->set(ComplexValue::decrease($by));
	}
	
	/**
	 * @param mixed $varName
	 * @return $this
	 */
	public function variable($varName)
	{
		return $this->set(ComplexValue::variable($varName));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function not($value)
	{
		return $this->set(ComplexValue::not($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function notField($value)
	{
		return $this->set(ComplexValue::notField($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function field($value)
	{
		return $this->set(ComplexValue::field($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function biggerEq($value)
	{
		return $this->set(ComplexValue::biggerEq($value));
		
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function smallerEq($value)
	{
		return $this->set(ComplexValue::smallerEq($value));
		
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function bigger($value)
	{
		return $this->set(ComplexValue::bigger($value));
	}
	
	/**
	 * @param mixed $value
	 * @return $this
	 */
	public function smaller($value)
	{
		return $this->set(ComplexValue::smaller($value));
		
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
			$this->set($value);
		}
		$Node = new OperatorNode($op);
		$this->set($Node);
		
		return $this->cloneThis();
	}
	
	/**
	 * @return $this
	 */
	public function notEmpty()
	{
		return $this->set(ComplexValue::notEmpty());
	}
	
	/**
	 * @return $this
	 */
	public function isEmpty()
	{
		return $this->set(ComplexValue::isEmpty());
	}
	
	/**
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return $this
	 */
	public function between($value1, $value2)
	{
		return $this->set(ComplexValue::between($value1, $value2));
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
		
		return $this->set(ComplexValue::between($from, $to));
	}
	
	/**
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return $this
	 */
	public function notBetween($value1, $value2)
	{
		return $this->set(ComplexValue::notBetween($value1, $value2));
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
		$this->set($int);
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function like($value, bool $fieldLower)
	{
		return $this->set(ComplexValue::like($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function likeP($value, bool $fieldLower)
	{
		return $this->set(ComplexValue::likeP($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function notLike($value, bool $fieldLower)
	{
		return $this->set(ComplexValue::notLike($value, $fieldLower));
	}
	
	/**
	 * @param mixed $value
	 * @param bool  $fieldLower
	 * @return $this
	 */
	public function notLikeP($value, bool $fieldLower)
	{
		return $this->set(ComplexValue::notLikeP($value, $fieldLower));
	}
	
	/**
	 * @param      $value
	 * @param bool $fieldLower - check field as lower
	 * @return $this
	 */
	public function string($value, bool $fieldLower = false)
	{
		return $this->set(ComplexValue::string($value, $fieldLower));
	}
	
	/**
	 * @return $this
	 */
	public function now()
	{
		return $this->set(ComplexValue::now());
	}
	
	/**
	 * Set field date
	 *
	 * @return $this
	 */
	public function date($date)
	{
		return $this->set(Date::toSqlDate($date));
	}
	
	/**
	 * Set field date
	 *
	 * @return $this
	 */
	public function dateField($date)
	{
		return $this->set(ComplexValue::dateField($date));
	}
	
	/**
	 * Round self value to field length determined in schema
	 *
	 * @return $this
	 */
	public function round($value)
	{
		return $this->set($this->Fields->Orm->Schema->round($this->field, $value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function md5($value)
	{
		return $this->set(md5($value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function json($value)
	{
		return $this->set(json_encode($value));
	}
	
	/**
	 * Set field value in md5
	 *
	 * @return $this
	 */
	public function compress($value)
	{
		return $this->set(ComplexValue::compress($value));
	}
	
	/**
	 * Set field value in md5 and check againts MD5(field)
	 *
	 * @return $this
	 */
	public function md5Field($value)
	{
		return $this->set(ComplexValue::md5Field($value));
	}
	
	/**
	 * Set field date time
	 *
	 * @return $this
	 */
	public function dateTime($dateTime)
	{
		return $this->set(Date::toSqlDateTime($dateTime));
	}
	
	/**
	 * Set field date time
	 *
	 * @return $this
	 */
	public function timestamp($dateTime)
	{
		return $this->set(Date::toTime($dateTime));
	}
	
	/**
	 * @return $this
	 */
	public function null()
	{
		return $this->set(ComplexValue::null());
	}
	
	/**
	 * @return $this
	 */
	public function int($var = 0)
	{
		return $this->set(intval($var));
	}
	
	/**
	 * @return $this
	 */
	public function notNull()
	{
		return $this->set(ComplexValue::notNull());
	}
	
	private function cloneThis()
	{
		$t                   = clone $this;
		$t->setValuesToGroup = array_key_last($this->Fields->getFieldValues($this->field));
		
		return $t;
	}
}

?>