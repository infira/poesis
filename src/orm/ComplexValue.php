<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\orm\node\Field;
use Infira\Poesis\Poesis;
use Infira\Utils\Date;

class ComplexValue
{
	/**
	 * @param $value
	 * @return \Infira\Poesis\orm\node\Field
	 */
	public static function simpleValue($value): Field
	{
		$field = self::typeField('simpleValue', $value);
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	//region value modifiers
	public static function md5($value): Field
	{
		$field = self::simpleValue(md5($value));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function compress($value): Field
	{
		$field = self::simpleValue($value);
		$field->addValueFunction('COMPRESS');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function increase(int $by): Field
	{
		$field = self::typeField('inDeCrease', $by);
		$field->setLogicalOperator('+');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function decrease(int $by): Field
	{
		$field = self::increase($by);
		$field->setLogicalOperator('-');
		
		return $field;
	}
	
	public static function json($value): Field
	{
		$field = self::simpleValue(json_encode($value));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function serialize($value): Field
	{
		$field = self::simpleValue(serialize($value));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function time($time): Field
	{
		$field = self::simpleValue(Date::from($time)->toNiceTime());
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function date($date): Field
	{
		$field = self::simpleValue(Date::from($date)->toSqlDate());
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function dateTime($date): Field
	{
		$field = self::simpleValue(Date::from($date)->toSqlDateTime());
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function timestamp($timestamp): Field
	{
		$field = self::simpleValue(Date::toTime($timestamp));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function int($value): Field
	{
		$field = self::simpleValue(intval($value));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function float($value): Field
	{
		$field = self::simpleValue(floatval($value));
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function boolInt($value): Field
	{
		$value = (bool)$value === true ? 1 : 0;
		
		return self::int($value);
	}
	//endregion
	
	//region raw values
	public static function raw(string $value): Field
	{
		$field = self::typeField('rawValue', trim($value));
		$field->setLogicalOperator('');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function query(string $query): Field
	{
		$field = self::raw($query);
		$field->setLogicalOperator('=');
		$field->setValuePrefix('(');
		$field->setValueSuffix(')');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function variable(string $varName): Field
	{
		$field = self::raw('@' . preg_replace("/[^a-zA-Z0-9_-]/", '', $varName));
		$field->setLogicalOperator('=');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function null(): Field
	{
		$field = self::typeField('null', null);
		$field->setLogicalOperator('IS');
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function column(string $column): Field
	{
		$field = self::typeField('compareColumn', $column);
		$field->setEditAllowed(true);
		
		return $field;
	}
	
	public static function now(): Field
	{
		$field = self::typeField('dateNow', 'now');
		$field->setEditAllowed(true);
		
		return $field;
	}
	//endregion
	
	//region select,delete complex value EDIT IS NOT ALLOWED
	public static function not($value): Field
	{
		$field = self::simpleValue($value);
		$field->setLogicalOperator('!=');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notNull(): Field
	{
		$field = self::null();
		$field->setLogicalOperator('IS NOT');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notColumn(string $column): Field
	{
		$field = self::column($column);
		$field->setLogicalOperator('!=');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function in($values): Field
	{
		$field = self::typeField('in', Variable::toArray($values));
		$field->setLogicalOperator('IN');
		$field->setValuePrefix('(');
		$field->setValueSuffix(')');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notIn($values): Field
	{
		$field = self::in($values);
		$field->setLogicalOperator('NOT IN');
		
		return $field;
	}
	
	public static function inSubQuery(string $query): Field
	{
		$field = self::query($query);
		$field->setLogicalOperator('IN');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notInSubQuery(string $query): Field
	{
		$field = self::inSubQuery($query);
		$field->setLogicalOperator('NOT IN');
		
		return $field;
	}
	
	public static function biggerEq($value): Field
	{
		$field = self::simpleValue($value);
		$field->setLogicalOperator('>=');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function smallerEq($value): Field
	{
		$field = self::simpleValue($value);
		$field->setLogicalOperator('<=');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function bigger($value): Field
	{
		$field = self::simpleValue($value);
		$field->setLogicalOperator('>');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function smaller($value): Field
	{
		$field = self::simpleValue($value);
		$field->setLogicalOperator('<');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function empty(): Field
	{
		$field = self::raw("''");
		$field->setLogicalOperator('=');
		$field->addColumnFunction('ifnull', ['']);
		$field->addColumnFunction('trim');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notEmpty(): Field
	{
		$field = self::empty();
		$field->setLogicalOperator('!=');
		
		return $field;
	}
	
	public static function between($value1, $value2): Field
	{
		$field = self::typeField('between', [$value1, $value2]);
		$field->setLogicalOperator('BETWEEN');
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notBetween($value1, $value2): Field
	{
		$field = self::between($value1, $value2);
		$field->setLogicalOperator('NOT BETWEEN');
		
		return $field;
	}
	
	public static function betweenColumns(string $column1, string $column2): Field
	{
		return self::between(ComplexValue::column($column1), ComplexValue::column($column2));
	}
	
	public static function notBetweenColumns(string $column1, string $column2): Field
	{
		$field = self::betweenColumns($column1, $column2);
		$field->setLogicalOperator('NOT BETWEEN');
		
		return $field;
	}
	
	public static function like($value): Field
	{
		$field = self::typeField('like', Poesis::UNDEFINED);
		$field->setLogicalOperator('LIKE');
		
		if ($value === null)
		{
			Poesis::error('like/not like cannot be null,use isNull instead');
		}
		
		$value = trim($value);
		if ($value[0] == "%")
		{
			$field->setValuePrefix('%');
			$value = substr($value, 1);
		}
		if (substr($value, -1) == "%")
		{
			$field->setValueSuffix('%');
			$value = substr($value, 0, -1);
		}
		$field->setValue($value);
		$field->setEditAllowed(false);
		
		return $field;
	}
	
	public static function notLike($value): Field
	{
		$field = self::like($value);
		$field->setLogicalOperator('NOT LIKE');
		
		return $field;
	}
	
	public static function likeP($value): Field
	{
		return self::like("%$value%");
	}
	
	public static function notLikeP($value): Field
	{
		$field = self::likeP($value);
		$field->setLogicalOperator('NOT LIKE');
		
		return $field;
	}
	
	public static function rLike($value): Field
	{
		$field = self::like($value);
		$field->setLogicalOperator('RLIKE');
		
		return $field;
	}
	
	public static function notRLike($value): Field
	{
		$field = self::like($value);
		$field->setLogicalOperator('NOT RLIKE');
		
		return $field;
	}
	//endregion
	
	//region ######################################### helpers
	
	private static function typeField(string $predicateType, $value): Field
	{
		if (!in_array($predicateType, ['in', 'between', 'between']) and is_array($value))
		{
			Poesis::error("Value can't be type array");
		}
		$field = new Field();
		$field->setPredicateType($predicateType);
		$field->setValue($value);
		
		return $field;
	}
	
	private static function isField($value): bool
	{
		return $value instanceof Field;
	}
	//endregion
}