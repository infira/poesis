<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\orm\node\Field;
use Infira\Poesis\Poesis;

class ComplexValue
{
	public static function simpleValue($value): Field
	{
		return self::typeField('simpleValue', $value);
	}
	
	public static function raw(string $value): Field
	{
		$field = self::typeField('rawValue', self::getSqlQuery($value));
		$field->setOperator('');
		
		return $field;
	}
	
	public static function in($values): Field
	{
		return self::typeField('in', Variable::toArray($values), 'IN');
	}
	
	public static function notIn($values): Field
	{
		return self::typeField('in', Variable::toArray($values), 'NOT IN');
	}
	
	public static function inSubQuery($query): Field
	{
		$field = self::typeField('inQuery', 'IN (' . self::getSqlQuery($query) . ')');
		$field->setOperator('');
		
		return $field;
	}
	
	public static function notInSubQuery($query): Field
	{
		$field = self::typeField('inQuery', 'NOT IN (' . self::getSqlQuery($query) . ')');
		$field->setOperator('');
		
		return $field;
	}
	
	public static function variable(string $varName): Field
	{
		return self::strictRawValue('@' . preg_replace("/[^a-zA-Z0-9_-]/", '', $varName));
	}
	
	public static function notNull(): Field
	{
		$field = self::strictRawValue(null);
		$field->setOperator('IS NOT');
		
		return $field;
	}
	
	public static function null(): Field
	{
		$field = self::strictRawValue(null);
		$field->setOperator('IS');
		
		return $field;
	}
	
	public static function query(string $query): Field
	{
		$field = self::typeField('sqlQuery', $query);
		$field->setValuePrefix('(');
		$field->setValueSuffix(')');
		
		return $field;
	}
	
	public static function not($value): Field
	{
		$field = self::simpleValue($value);
		$field->setOperator('!=');
		$field->setValue($value);
		
		return $field;
	}
	
	public static function notColumn(string $column): Field
	{
		return self::typeField('compareColumn', $column, '!=');
	}
	
	public static function column(string $columns): Field
	{
		return self::typeField('compareColumn', $columns, '=');
	}
	
	public static function biggerEq($value): Field
	{
		$field = self::simpleValue($value);
		$field->setOperator('>=');
		
		return $field;
	}
	
	public static function smallerEq($value): Field
	{
		$field = self::simpleValue($value);
		$field->setOperator('<=');
		
		return $field;
	}
	
	public static function bigger($value): Field
	{
		$field = self::simpleValue($value);
		$field->setOperator('>');
		
		return $field;
	}
	
	public static function smaller($value): Field
	{
		$field = self::simpleValue($value);
		$field->setOperator('<');
		
		return $field;
	}
	
	public static function md5($value, bool $convertColumnToMD5 = false): Field
	{
		$field = self::simpleValue(md5($value));
		if ($convertColumnToMD5)
		{
			$field->addColumnsFunction('MD5');
		}
		
		return $field;
	}
	
	public static function compress($value): Field
	{
		$field = self::simpleValue($value);
		$field->addColumnsFunction('COMPRESS');
		
		return $field;
	}
	
	public static function notEmpty(): Field
	{
		$field = self::strictRawValue("''");
		$field->setOperator('!=');
		$field->addColumnsFunction('ifnull', ['']);
		$field->addColumnsFunction('trim');
		
		return $field;
	}
	
	public static function isEmpty(): Field
	{
		$field = self::strictRawValue("''");
		$field->setOperator('=');
		$field->addColumnsFunction('ifnull', ['']);
		$field->addColumnsFunction('trim');
		
		return $field;
	}
	
	public static function betweenColumns(string $column1, string $column2): Field
	{
		return self::typeField('betweenColumns', [$column1, $column2], 'BETWEEN');
	}
	
	public static function notBetweenColumns($column1, $column2): Field
	{
		return self::typeField('betweenColumns', [$column1, $column2], 'NOT BETWEEN');
	}
	
	public static function between($value1, $value2): Field
	{
		return self::typeField('between', [$value1, $value2], 'BETWEEN');
	}
	
	public static function notBetween($value1, $value2): Field
	{
		return self::typeField('between', [$value1, $value2], 'NOT BETWEEN');
	}
	
	public static function likeP($value): Field
	{
		return self::likeField($value, 'LIKE', true);
	}
	
	public static function notLikeP($value): Field
	{
		return self::likeField($value, 'NOT LIKE', true);
	}
	
	public static function like($value): Field
	{
		return self::likeField($value, 'LIKE', false);
	}
	
	public static function notLike($value): Field
	{
		return self::likeField($value, 'NOT LIKE', false);
	}
	
	public static function now($logicalOperator = '='): Field
	{
		$field = self::strictRawValue('NOW()');
		if (in_array($logicalOperator, ['=', '<', '>', '<=', '>='], true))
		{
			$field->setOperator($logicalOperator);
		}
		
		return $field;
	}
	
	public static function increase($by): Field
	{
		return self::typeField('inDeCrease', $by, '+');
	}
	
	public static function decrease($by): Field
	{
		return self::typeField('inDeCrease', $by, '-');
	}
	
	//region ######################################### helpers
	private static function getSqlQuery($value): string
	{
		if (is_object($value))
		{
			$value = $value->getSelectQuery();
		}
		
		return $value;
	}
	
	private static function strictRawValue($value): Field
	{
		return self::typeField('strictRawValue', $value);
	}
	
	private static function typeField(string $type, $value, string $operator = null): Field
	{
		if ($type == 'in' and !checkArray($value))
		{
			Poesis::error('Cant provide empty array');
		}
		$field = new Field();
		$field->setPredicateType($type);
		$field->setValue($value);
		if ($operator !== null)
		{
			$field->setOperator($operator);
		}
		
		return $field;
	}
	
	private static function likeField($value, string $operator, bool $surroudnP): Field
	{
		$field = new Field(Poesis::UNDEFINED);
		$field->setPredicateType('like');
		$field->setOperator($operator);
		
		if ($value === null)
		{
			Poesis::error('like/not like cannot be null,use isNull instead');
		}
		
		$value = trim($value);
		if ($surroudnP)
		{
			$field->setValueSuffix('%');
			$field->setValuePrefix('%');
		}
		else
		{
			if ($value{0} == "%")
			{
				$field->setValueSuffix('%');
				$value = substr($value, 1);
			}
			if (substr($value, -1) == "%")
			{
				$field->setValuePrefix('%');
				$value = substr($value, 0, -1);
			}
		}
		$field->setValue($value);
		
		return $field;
	}
	//endregion
}

?>