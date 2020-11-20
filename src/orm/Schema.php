<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\Poesis;
use stdClass;

trait Schema
{
	protected static $name;
	protected static $fields         = [];
	protected static $tableName;
	protected static $className;
	protected static $primaryFields;
	protected static $aiField;
	protected static $isView;
	protected static $fieldStructure = [];
	
	/**
	 * Get alla the fields
	 *
	 * @return array
	 */
	public static function getFields(): array
	{
		return self::$fields;
	}
	
	/**
	 * Get table databse table name
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return self::$tableName;
	}
	
	/**
	 * Get table databse class name
	 *
	 * @return string
	 */
	public static function getClassName()
	{
		return self::$className;
	}
	
	/**
	 * Get table databse class object
	 *
	 * @return Model
	 */
	public static function getClassObject()
	{
		$className = self::$className;
		
		return new $className();
	}
	
	/**
	 * Has any primary fields
	 *
	 * @return string
	 */
	public static function hasPrimaryFields()
	{
		return (count(self::$primaryFields) > 0);
	}
	
	/**
	 * Get table primary field name
	 *
	 * @return string
	 */
	public static function getPrimaryFields()
	{
		return self::$primaryFields;
	}
	
	/**
	 * Does structure has auto increment field
	 *
	 * @return bool
	 */
	public static function hasAIField()
	{
		return (empty(self::$aiField)) ? false : true;
	}
	
	/**
	 * Get auto increment field name
	 *
	 * @return bool|string
	 */
	public static function getAIField()
	{
		return self::$aiField;
	}
	
	/**
	 * Check if the tabele is view
	 *
	 * @return bool
	 */
	public static function isView()
	{
		return self::$isView;
	}
	
	
	/**
	 * Get field info
	 *
	 * @param string $field
	 * @return array
	 */
	public static function getFieldStructure(string $field): array
	{
		return self::$fieldStructure[$field];
	}
	
	/**
	 * Get field info
	 *
	 * @param string $field
	 * @param string $type
	 * @return mixed
	 */
	public static function getFieldStructureEntity(string $field, string $type)
	{
		return self::getFieldStructure($field)[$type];
	}
	
	/**
	 * Get field $typeName
	 *
	 * @param string $field
	 * @return string
	 */
	public static function getType(string $field): string
	{
		return Variable::toLower(self::getFieldStructureEntity($field, "type"));
	}
	
	/**
	 * Get field length
	 *
	 * @param string $field
	 * @return int|array
	 */
	public static function getLength(string $field)
	{
		return self::getFieldStructureEntity($field, "length");
	}
	
	/**
	 * Get number decimal precision
	 *
	 * @param string $field
	 * @return int
	 */
	public static function getRoundPrecision(string $field)
	{
		return self::getLength($field)['p'];
	}
	
	
	/**
	 * Round to correct length
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @return mixed
	 */
	public static function round(string $field, $value)
	{
		$p = self::getFieldStructureEntity($field, "length")['p'];
		
		return round($value, $p);
	}
	
	/**
	 * Get field default value
	 *
	 * @param string $field
	 * @return mixed
	 */
	public static function getDefaultValue(string $field)
	{
		return self::getFieldStructureEntity($field, "default");
	}
	
	/**
	 * Get field $typeName
	 *
	 * @param string $field
	 * @return array
	 */
	public static function getAllowedValues(string $field): array
	{
		return self::getFieldStructureEntity($field, "allowedValues");
	}
	
	/**
	 * Is $field null value allowed
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function isNullAllowed(string $field): bool
	{
		return self::getFieldStructureEntity($field, "isNull");
	}
	
	/**
	 * Is $field unsigned
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function isSigned(string $field): bool
	{
		return self::getFieldStructureEntity($field, "signed");
	}
	
	/**
	 * Is $field a auto increment field
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function isAI(string $field): bool
	{
		if (self::fieldExists($field))
		{
			return false;
		}
		
		return self::getFieldStructureEntity($field, "isAI");
	}
	
	/**
	 * Get table primari field name
	 *
	 * @param array $defaultValues
	 * @return stdClass
	 */
	public static function getFieldNamesObject($defaultValues = [])
	{
		$obj = new stdClass();
		foreach (self::getFields() as $fieldName)
		{
			$obj->$fieldName = "";
			if (isset($defaultValues[$fieldName]))
			{
				$obj->$fieldName = $defaultValues[$fieldName];
			}
		}
		
		return $obj;
	}
	
	/**
	 * Get table primari field name
	 *
	 * @param array $defaultValues
	 * @return array
	 */
	public static function getFieldNames($defaultValues = []): array
	{
		return (array)self::getFieldNamesObject($defaultValues);
	}
	
	/**
	 * Alerts when fields does not exist
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function checkField(string $field): bool
	{
		if (!self::isRawField($field) and !in_array($field, self::$fields))
		{
			addExtraErrorInfo('self::$name', self::$name);
			addExtraErrorInfo('self::$fields', self::$fields);
			Poesis::error('DbOrm field <B>"' . self::getTableName() . '.' . $field . '</B>" does not exists');
		}
		
		return true;
	}
	
	
	/**
	 * Check if the field exits in table class
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function fieldExists(string $field): bool
	{
		if (self::isRawField($field))
		{
			return true;
		}
		
		return in_array($field, self::$fields);
	}
	
	/**
	 * Check is field raw query field
	 *
	 * @param string $field
	 * @return bool
	 */
	public static function isRawField(string $field): bool
	{
		return (substr($field, 0, 21) == QueryCompiler::RAW_QUERY_FIELD);
	}
	
	
	/**
	 * Get table databse with $field name init
	 *
	 * @param string $field
	 * @return string
	 */
	public static function getTableField(string $field): string
	{
		return self::$tableName . "." . $field;
	}
}

?>