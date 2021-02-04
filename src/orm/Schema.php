<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\Poesis;
use Infira\Poesis\QueryCompiler;

trait Schema
{
	protected static $name;
	protected static $columns         = [];
	protected static $tableName;
	protected static $className;
	protected static $primaryColumns;
	protected static $aiColumn;
	protected static $isView;
	protected static $columnStructure = [];
	
	/**
	 * Get alla the columns
	 *
	 * @return array
	 */
	public static function getColumns(): array
	{
		return self::$columns;
	}
	
	/**
	 * Does current model has $column
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function hasColumn(string $column): bool
	{
		return in_array($column, self::$columns);
	}
	
	/**
	 * Get table databse table name
	 *
	 * @return string
	 */
	public static function getTableName(): string
	{
		return self::$tableName;
	}
	
	/**
	 * Get table databse class name
	 *
	 * @return string
	 */
	public static function getClassName(): string
	{
		return self::$className;
	}
	
	/**
	 * Make model
	 *
	 * @return Model
	 */
	public static function makeModel(): Model
	{
		$cn = self::$className;
		
		return new $cn(...func_get_args());
	}
	
	/**
	 * Get table databse class object
	 *
	 * @return Model
	 */
	public static function getClassObject(): Model
	{
		$className = self::$className;
		
		return new $className();
	}
	
	/**
	 * Check is $column a primary column
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function isPrimaryColumn(string $column): bool
	{
		return in_array($column, self::$primaryColumns);
	}
	
	/**
	 * Has any primary columns
	 *
	 * @return string
	 */
	public static function hasPrimaryColumns()
	{
		return (count(self::$primaryColumns) > 0);
	}
	
	/**
	 * Get table primary column names
	 *
	 * @return array
	 */
	public static function getPrimaryColumns(): array
	{
		return self::$primaryColumns;
	}
	
	/**
	 * Does structure has auto increment column
	 *
	 * @return bool
	 */
	public static function hasAIColumn(): bool
	{
		return (empty(self::$aiColumn)) ? false : true;
	}
	
	/**
	 * Get auto increment column name
	 *
	 * @return null|string
	 */
	public static function getAIColumn(): ?string
	{
		return self::$aiColumn;
	}
	
	/**
	 * Check if the tabele is view
	 *
	 * @return bool
	 */
	public static function isView(): bool
	{
		return self::$isView;
	}
	
	
	/**
	 * Get column info
	 *
	 * @param string $column
	 * @return array
	 */
	public static function getColumnStructure(string $column): array
	{
		return self::$columnStructure[$column];
	}
	
	/**
	 * Get column info bye type
	 *
	 * @param string $column
	 * @param string $type
	 * @return mixed
	 */
	public static function getColumnStructureEntity(string $column, string $type)
	{
		return self::getColumnStructure($column)[$type];
	}
	
	/**
	 * Get $column type
	 *
	 * @param string $column
	 * @return string
	 */
	public static function getType(string $column): string
	{
		return Variable::toLower(self::getColumnStructureEntity($column, "type"));
	}
	
	
	/**
	 * Get $column fix type for QueryCompiler
	 *
	 * @param string $column
	 * @return string
	 */
	public static function getFixType(string $column): string
	{
		$type = self::getType($column);
		if (preg_match('/int/i', $type))
		{
			return 'int';
		}
		elseif (in_array($type, ['decimal']))
		{
			return 'decimal';
		}
		elseif (in_array($type, ['timestamp', 'time', 'year']) or preg_match('/date/i', $type))
		{
			return 'dateTime';
		}
		elseif (in_array($type, ['float', 'double', 'real']))
		{
			return 'float';
		}
		
		return 'string';
	}
	
	/**
	 * Get $column length
	 *
	 * @param string $column
	 * @return int|array
	 */
	public static function getLength(string $column)
	{
		return self::getColumnStructureEntity($column, "length");
	}
	
	/**
	 * Get number decimal precision
	 *
	 * @param string $column
	 * @return int
	 */
	public static function getRoundPrecision(string $column): int
	{
		return self::getLength($column)['p'];
	}
	
	
	/**
	 * Round to correct length
	 *
	 * @param string $column
	 * @param mixed  $value
	 * @return mixed
	 */
	public static function round(string $column, $value)
	{
		$p = self::getColumnStructureEntity($column, "length")['p'];
		
		return round($value, $p);
	}
	
	/**
	 * Get $column default value
	 *
	 * @param string $column
	 * @return mixed
	 */
	public static function getDefaultValue(string $column)
	{
		return self::getColumnStructureEntity($column, "default");
	}
	
	/**
	 * Get $column allowed values
	 *
	 * @param string $column
	 * @return array
	 */
	public static function getAllowedValues(string $column): array
	{
		return self::getColumnStructureEntity($column, "allowedValues");
	}
	
	/**
	 * Is $column null value allowed
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function isNullAllowed(string $column): bool
	{
		return self::getColumnStructureEntity($column, "isNull");
	}
	
	/**
	 * Is $column unsigned
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function isSigned(string $column): bool
	{
		return self::getColumnStructureEntity($column, "signed");
	}
	
	/**
	 * Is $column a auto increment column
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function isAI(string $column): bool
	{
		if (self::columnExists($column))
		{
			return false;
		}
		
		return self::getColumnStructureEntity($column, "isAI");
	}
	
	/**
	 * Alerts when $column does not exist
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function checkColumn(string $column): bool
	{
		if ($column !== QueryCompiler::RAW_QUERY and !in_array($column, self::$columns))
		{
			addExtraErrorInfo('self::$name', self::$name);
			addExtraErrorInfo('self::$columns', self::$columns);
			Poesis::error('DbOrm column <B>"' . self::getTableName() . '.' . $column . '</B>" does not exists');
		}
		
		return true;
	}
	
	/**
	 * Check if the $column exits in table class
	 *
	 * @param string $column
	 * @return bool
	 */
	public static function columnExists(string $column): bool
	{
		if ($column === QueryCompiler::RAW_QUERY)
		{
			return true;
		}
		
		return in_array($column, self::$columns);
	}
	
	
	/**
	 * Get table databse with $column name init
	 *
	 * @param string $column
	 * @return string
	 */
	public static function makeJoinTableName(string $column): string
	{
		return self::$tableName . "." . $column;
	}
}

?>