<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\orm\node\ValueNode;
use Infira\Utils\Date;

class ComplexValue
{
	public static function simpleValue($value): ValueNode
	{
		return self::typeNode('simpleValue', $value);
	}
	
	public static function raw(string $value): ValueNode
	{
		$node = self::rawValueNode(self::getSqlQuery($value));
		$node->setOperator('');
		
		return $node;
	}
	
	public static function in($values): ValueNode
	{
		return self::typeNode('in', Variable::toArray($values), 'IN');
	}
	
	public static function notIn($values): ValueNode
	{
		return self::typeNode('in', Variable::toArray($values), 'NOT IN');
	}
	
	public static function inSubQuery($query): ValueNode
	{
		return self::raw('IN (' . self::getSqlQuery($query) . ')');
	}
	
	public static function notInSubQuery($query): ValueNode
	{
		return self::raw('NOT IN (' . self::getSqlQuery($query) . ')');
	}
	
	public static function variable(string $varName): ValueNode
	{
		return self::rawValueNode('@' . preg_replace("/[^a-zA-Z0-9_-]/", '', $varName));
	}
	
	public static function notNull(): ValueNode
	{
		$node = self::rawValueNode('NULL');
		$node->setOperator('IS NOT');
		
		return $node;
	}
	
	public static function null(): ValueNode
	{
		$node = self::rawValueNode('NULL');
		$node->setOperator('IS');
		
		return $node;
	}
	
	public static function not($value): ValueNode
	{
		$node = self::simpleValue($value);
		if ($value === null)
		{
			$node->setOperator('IS NOT');
		}
		else
		{
			$node->setOperator('!=');
		}
		$node->set($value);
		
		return $node;
	}
	
	public static function notField(string $field): ValueNode
	{
		return self::typeNode('compareField', $field, '!=');
	}
	
	public static function field(string $field): ValueNode
	{
		return self::typeNode('compareField', $field, '=');
	}
	
	public static function biggerEq($value): ValueNode
	{
		$node = self::simpleValue($value);
		$node->setOperator('>=');
		
		return $node;
	}
	
	public static function smallerEq($value): ValueNode
	{
		$node = self::simpleValue($value);
		$node->setOperator('<=');
		
		return $node;
	}
	
	public static function bigger($value): ValueNode
	{
		$node = self::simpleValue($value);
		$node->setOperator('>');
		
		return $node;
	}
	
	public static function smaller($value): ValueNode
	{
		$node = self::simpleValue($value);
		$node->setOperator('<');
		
		return $node;
	}
	
	public static function md5($value, bool $convertFieldToMD5 = false): ValueNode
	{
		$node = self::simpleValue(md5($value));
		if ($convertFieldToMD5)
		{
			$node->addFieldFunction('MD5');
		}
		
		return $node;
	}
	
	public static function compress($value): ValueNode
	{
		$node = self::simpleValue($value);
		$node->addFieldFunction('COMPRESS');
		
		return $node;
	}
	
	public static function notEmpty(): ValueNode
	{
		$node = self::rawValueNode("''");
		$node->setOperator('!=');
		$node->addFieldFunction('ifnull', ['']);
		$node->addFieldFunction('trim');
		
		return $node;
	}
	
	public static function isEmpty(): ValueNode
	{
		$node = self::rawValueNode("''");
		$node->setOperator('=');
		$node->addFieldFunction('ifnull', ['']);
		$node->addFieldFunction('trim');
		
		return $node;
	}
	
	public static function betweenFields(string $field1, string $field2): ValueNode
	{
		return self::typeNode('betweenFields', [$field1, $field2], 'BETWEEN');
	}
	
	public static function notBetweenFields($field1, $field2): ValueNode
	{
		return self::typeNode('betweenFields', [$field1, $field2], 'NOT BETWEEN');
	}
	
	public static function between($value1, $value2): ValueNode
	{
		return self::typeNode('between', [$value1, $value2], 'BETWEEN');
	}
	
	public static function notBetween($value1, $value2): ValueNode
	{
		return self::typeNode('between', [$value1, $value2], 'NOT BETWEEN');
	}
	
	public static function likeP($value): ValueNode
	{
		return self::likeNode($value, 'LIKE', true);
	}
	
	public static function notLikeP($value): ValueNode
	{
		return self::likeNode($value, 'NOT LIKE', true);
	}
	
	public static function like($value): ValueNode
	{
		return self::likeNode($value, 'LIKE', false);
	}
	
	public static function notLike($value): ValueNode
	{
		return self::likeNode($value, 'NOT LIKE', false);
	}
	
	public static function now(): ValueNode
	{
		return self::rawValueNode('NOW()');
	}
	
	public static function increase($by): ValueNode
	{
		return self::typeNode('inDeCrease', $by, '+');
	}
	
	public static function decrease($by): ValueNode
	{
		return self::typeNode('inDeCrease', $by, '-');
	}
	
	//####################################### helpers
	private static function getSqlQuery($value): string
	{
		if (is_object($value))
		{
			$value = $value->getSelectQuery();
		}
		
		return $value;
	}
	
	private static function rawValueNode($value): ValueNode
	{
		return self::typeNode('rawValue', $value);
	}
	
	private static function typeNode(string $type, $value, string $operator = null): ValueNode
	{
		$node = new ValueNode();
		$node->setType($type);
		$node->set($value);
		if ($operator !== null)
		{
			$node->setOperator($operator);
		}
		
		return $node;
	}
	
	
	private static function likeNode($value, string $operator, bool $surroudnP): ValueNode
	{
		$node = new ValueNode();
		$node->setType('like');
		$node->setOperator($operator);
		
		$value = trim($value);
		if ($surroudnP)
		{
			$node->setValueSuffix('%');
			$node->setValuePrefix('%');
		}
		else
		{
			if ($value{0} == "%")
			{
				$node->setValueSuffix('%');
				$value = substr($value, 1);
			}
			if (substr($value, -1) == "%")
			{
				$node->setValuePrefix('%');
				$value = substr($value, 0, -1);
			}
			if ($surroudnP)
			{
				$node->setValueSuffix('%');
				$node->setValuePrefix('%');
			}
		}
		$node->set($value);
		
		return $node;
	}
}

?>