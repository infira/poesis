<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;

trait Schema
{
	protected static $tableName;
	protected static $dbName;
	protected static $fullTableName;
	protected static $columns         = [];
	protected static $modelClass;
	protected static $columnClass;
	protected static $modelName;
	protected static $primaryColumns;
	protected static $aiColumn;
	protected static $TIDColumn       = false;
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
	 * Get table name
	 *
	 * @return string
	 */
	public static function getTableName(): string
	{
		return self::$tableName;
	}
	
	/**
	 * get model name
	 *
	 * @return string
	 */
	public static function getModelName(): string
	{
		return self::$modelName;
	}
	
	public static function getModuleColumnClass(): string
	{
		return self::$columnClass;
	}
	
	/**
	 * Make model
	 *
	 * @param array $options
	 * @return Model
	 */
	public static function makeModel(array $options = []): Model
	{
		$cn = self::$modelClass;
		
		return new $cn($options);
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
	
	public static function hasPrimaryColumns(): bool
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
		return !empty(self::$aiColumn);
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
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	public static function hasTIDColumn(): bool
	{
		return self::$TIDColumn !== null;
	}
	
	/**
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	public static function isTIDEnabled(): bool
	{
		return Poesis::isTIDEnabled() and self::$TIDColumn !== null;
	}
	
	/**
	 * Get TID column name
	 *
	 * @return string
	 */
	public static function getTIDColumn(): ?string
	{
		return self::$TIDColumn;
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
		return strtolower(self::getColumnStructureEntity($column, "type"));
	}
	
	/**
	 * Get column type according to available types IN PHP (decimal,float,double,real) as float,
	 * (int,tinyInt,bigIn,...) as int, and so on
	 *
	 * @param string $column
	 * @return string
	 */
	public static function getCoreType(string $column): string
	{
		$type = self::getType($column);
		if (preg_match('/int/i', $type)) {
			return 'int';
		}
		elseif (in_array($type, ['decimal', 'float', 'double', 'real'])) {
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
	 * @return float
	 */
	public static function round(string $column, float $value): float
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
		if (self::columnExists($column)) {
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
		if (!in_array($column, self::$columns) and (self::$TIDColumn and $column != 'TID' and Poesis::isTIDEnabled())) {
			$extra                   = [];
			$extra['self::$name']    = self::$modelClass;
			$extra['self::$columns'] = self::$columns;
			Poesis::error('Db column <strong>"' . self::getTableName() . '.' . $column . '</strong>" does not exists', $extra);
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
		return in_array($column, self::$columns);
	}
}