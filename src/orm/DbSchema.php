<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Date;

/**
 * @property array $structure
 */
abstract class DbSchema
{
	
	/**
	 * Get column info
	 *
	 * @param string $index
	 * @return array
	 */
	public function getColumnStructure(string $index): array
	{
		[$table, $column] = explode('.', $index);
		
		return $this->structure[$table][$column];
	}
	
	/**
	 * Get column info bye type
	 *
	 * @param string $index
	 * @param string $type
	 * @return mixed
	 */
	public function getColumnStructureEntity(string $index, string $type)
	{
		return $this->getColumnStructure($index)[$type];
	}
	
	/**
	 * Get $index type
	 *
	 * @param string $index
	 * @return string
	 */
	public function getType(string $index): string
	{
		return strtolower($this->getColumnStructureEntity($index, "type"));
	}
	
	/**
	 * Get column type according to available types IN PHP (decimal,float,double,real) as float,
	 * (int,tinyInt,bigIn,...) as int, and so on
	 *
	 * @param string $index
	 * @return string
	 */
	public function getCoreType(string $index): string
	{
		$type = $this->getType($index);
		if (preg_match('/int/i', $type)) {
			return 'int';
		}
		elseif (in_array($type, ['decimal', 'float', 'double', 'real'])) {
			return 'float';
		}
		
		return 'string';
	}
	
	/**
	 * Get $index length
	 *
	 * @param string $index
	 * @return int|array
	 */
	public function getLength(string $index)
	{
		return $this->getColumnStructureEntity($index, "length");
	}
	
	/**
	 * Get number decimal precision
	 *
	 * @param string $index
	 * @return int
	 */
	public function getRoundPrecision(string $index): int
	{
		return $this->getLength($index)['p'];
	}
	
	
	/**
	 * Round to correct length
	 *
	 * @param string $index
	 * @param mixed  $value
	 * @return float
	 */
	public function round(string $index, float $value): float
	{
		$p = $this->getColumnStructureEntity($index, "length")['p'];
		
		return round($value, $p);
	}
	
	/**
	 * Get $index default value
	 *
	 * @param string $index
	 * @return mixed
	 */
	public function getDefaultValue(string $index)
	{
		return $this->getColumnStructureEntity($index, "default");
	}
	
	/**
	 * Get $index allowed values
	 *
	 * @param string $index
	 * @return array
	 */
	public function getAllowedValues(string $index): array
	{
		return $this->getColumnStructureEntity($index, "allowedValues");
	}
	
	/**
	 * Is $index null value allowed
	 *
	 * @param string $index
	 * @return bool
	 */
	public function isNullAllowed(string $index): bool
	{
		return $this->getColumnStructureEntity($index, "isNull");
	}
	
	/**
	 * Is $index unsigned
	 *
	 * @param string $index
	 * @return bool
	 */
	public function isSigned(string $index): bool
	{
		return $this->getColumnStructureEntity($index, "signed");
	}
	
	/**
	 * Is $index a auto increment column
	 *
	 * @param string $index
	 * @return bool
	 */
	public function isAI(string $index): bool
	{
		if ($this->exists($index)) {
			return false;
		}
		
		return $this->getColumnStructureEntity($index, "isAI");
	}
	
	/**
	 * Check if the $index exits in table class
	 *
	 * @param string $index
	 * @return bool
	 */
	public function exists(string $index): bool
	{
		[$table, $column] = explode('.', $index);
		
		return isset($this->structure[$table][$column]);
	}
	
	/**
	 * Will convert integer to integer, (float,double,real,decimal) to float, and so on
	 * In case of interger type will
	 *
	 * @param string $index
	 * @param        $value
	 * @throws \Exception
	 * @return float|int|mixed
	 */
	public function fixValueByColumnType(string $index, $value) //TODO this shoule be part of DbFix or something?
	{
		$type     = $this->getType($index);
		$coreType = $this->getCoreType($index);
		if ($coreType == 'int') {
			return intval($value);
		}
		elseif ($coreType == 'float') {
			return floatval($value);
		}
		elseif ($type == 'date') {
			return Date::of($value)->toSqlDate();
		}
		elseif (in_array($type, ['datetime', 'timestamp'])) {
			return Date::of($value)->toSqlDateTime();
		}
		
		return $value;
	}
	
}