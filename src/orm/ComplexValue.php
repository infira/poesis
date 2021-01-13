<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;

class ComplexValue
{
	public static function simpleValue($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->value               = $value;
		$Output->function            = 'simpleValue';
		
		return $Output;
	}
	
	public static function in($values)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'in';
		$v                           = [];
		if (is_array($values))
		{
			//array_walk($values)
		}
		$Output->value = Variable::toArray($values);
		
		return $Output;
	}
	
	public static function notIn($values)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'not in';
		$Output->value               = Variable::toArray($values);
		
		return $Output;
	}
	
	public static function inSubQuery($query)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'inSubQuery';
		$Output->value               = self::getSqlQuery($query);
		
		return $Output;
	}
	
	public static function notInSubQuery($query)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'notInSubQuery';
		$Output->value               = self::getSqlQuery($query);
		
		return $Output;
	}
	
	public static function variable($varName)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'sqlvar';
		$Output->value               = $varName;
		
		return $Output;
	}
	
	public static function notNull()
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'notnull';
		$Output->value               = null;
		
		return $Output;
	}
	
	public static function not($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'not';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function notField($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'notField';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function field($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'field';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function biggerEq($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = '>=';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function smallerEq($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = '<=';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function bigger($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = '>';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function smaller($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = '<';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function md5Field($value, $md5Value = true)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'md5Field';
		$Output->md5Value            = $md5Value;
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function compress($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'compress';
		$Output->value               = $value;
		
		return $Output;
	}
	
	private static function getSqlQuery($value)
	{
		if (is_object($value))
		{
			$value = $value->getSelectQuery();
		}
		
		return $value;
	}
	
	public static function query($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'rawQuery';
		$Output->value               = self::getSqlQuery($value);
		
		return $Output;
	}
	
	public static function force($value)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'force';
		$Output->value               = $value;
		
		return $Output;
	}
	
	public static function increase($by)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'increase';
		$Output->value               = $by;
		
		return $Output;
	}
	
	public static function decrease($by)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'decrease';
		$Output->value               = $by;
		
		return $Output;
	}
	
	public static function notEmpty()
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'notEmpty';
		$Output->value               = '';
		
		return $Output;
	}
	
	public static function isEmpty()
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'empty';
		$Output->value               = '';
		
		return $Output;
	}
	
	public static function between($value1, $value2)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'between';
		$Output->value               = [$value1, $value2];
		
		return $Output;
	}
	
	public static function notBetween($value1, $value2)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'notbetween';
		$Output->value               = [$value1, $value2];
		
		return $Output;
	}
	
	private static function __likeQuery($value, bool $fieldLower, string $function, bool $surroudnP)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = $function;
		$Output->fieldLower          = $fieldLower;
		$Output->addLeftP            = false;
		$Output->addRightP           = false;
		
		$value = trim($value);
		if ($value{0} == "%")
		{
			$Output->addLeftP = true;
			$value            = substr($value, 1);
		}
		if (substr($value, -1) == "%")
		{
			$Output->addRightP = true;
			$value             = substr($value, 0, -1);
		}
		if ($surroudnP)
		{
			$Output->addLeftP  = true;
			$Output->addRightP = true;
		}
		$Output->value = $value;
		
		return $Output;
	}
	
	public static function likeP($value, $fieldLower)
	{
		return self::__likeQuery($value, $fieldLower, 'like', true);
	}
	
	public static function notLikeP($value, $fieldLower)
	{
		return self::__likeQuery($value, $fieldLower, 'notLike', true);
	}
	
	public static function like($value, $fieldLower)
	{
		return self::__likeQuery($value, $fieldLower, 'like', false);
	}
	
	public static function notLike($value, $fieldLower)
	{
		return self::__likeQuery($value, $fieldLower, 'notlike', true);
	}
	
	public static function string($value, $fieldLower = false)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'str';
		$Output->value               = $value;
		$Output->fieldLower          = $fieldLower;
		
		return $Output;
	}
	
	public static function now()
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'now';
		$Output->value               = 'now()';
		
		return $Output;
	}
	
	public static function null()
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'null';
		$Output->value               = 'null';
		
		return $Output;
	}
	
	public static function dateField($date)
	{
		$Output                      = new \stdClass();
		$Output->__dbORMComplexValue = true;
		$Output->function            = 'datefield';
		$Output->value               = Date::toSqlDate($date);
		
		return $Output;
	}
}

?>