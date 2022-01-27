<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;
use Infira\Utils\Variable;
use Infira\Utils\Is;
use Infira\Utils\Date;
use Infira\Poesis\support\QueryCompiler;
use Infira\Poesis\support\RepoTrait;

//Infira\Utils TODO tuleks ära võtta

class Field
{
	use RepoTrait;
	
	private $editAllowed = null;
	private $schemaIndex = '';//$table.index;
	private $table       = '';
	private $column      = '';
	private $finalColumn = '';
	private $value       = Poesis::UNDEFINED;
	
	private $columnFunction = [];
	private $valueFunction  = [];
	private $predicateType  = '';
	private $valuePrefix    = null;
	private $valueSuffix    = null;
	
	/**
	 * @see https://dev.mysql.com/doc/refman/8.0/en/non-typed-operators.html
	 * @var string
	 */
	private $comparison = '=';
	
	public function __construct(string $column = null, $value = Poesis::UNDEFINED)
	{
		$this->column = $column;
		$this->value  = $value;
	}
	
	public function getValue()
	{
		return $this->value;
	}
	
	public function setValue($value)
	{
		$this->value = $value;
	}
	
	public function setConnectionName(string $name)
	{
		$this->connectionName = $name;
	}
	
	public function getConnectionName(): string
	{
		return $this->connectionName;
	}
	
	/**
	 * @return string
	 */
	public function getValuePrefix(): ?string
	{
		return $this->valuePrefix;
	}
	
	/**
	 * @param string $valuePrefix
	 */
	public function setValuePrefix(string $valuePrefix): void
	{
		$this->valuePrefix = $valuePrefix;
	}
	
	/**
	 * @return string
	 */
	public function getValueSuffix(): ?string
	{
		return $this->valueSuffix;
	}
	
	/**
	 * @param string $valueSuffix
	 */
	public function setValueSuffix(string $valueSuffix): void
	{
		$this->valueSuffix = $valueSuffix;
	}
	
	public function setPredicateType(string $type)
	{
		if (empty($type)) {
			Poesis::error('type can\'t be empty');
		}
		$this->predicateType = $type;
	}
	
	public function getPredicateType(): string
	{
		return $this->predicateType;
	}
	
	public function isPredicateType(string $type): bool
	{
		return in_array(strtolower($this->predicateType), Variable::toArray(strtolower($type)));
	}
	
	public function getComparison(): string
	{
		return $this->comparison;
	}
	
	public function setComparsion(string $operator): void
	{
		$this->comparison = $operator;
	}
	
	public function getColumn(): string
	{
		return $this->column;
	}
	
	public function setEditAllowed(bool $bool)
	{
		$this->editAllowed = $bool;
	}
	
	public function isEditAllowed(): bool
	{
		return $this->editAllowed;
	}
	
	public function getFinalColumn(): string
	{
		return $this->finalColumn;
	}
	
	public function setSchemaIndex(string $table, string $column)
	{
		$this->table       = $table;
		$this->schemaIndex = "$table.$column";
	}
	
	/**
	 * @param string $column
	 * @param null   $columnForFinalQuery - what is the column name what is puted to final query, if null it will be same as $column
	 */
	public function setColumn(string $column, $columnForFinalQuery = null)
	{
		$this->column      = $column;
		$this->finalColumn = $columnForFinalQuery ?: $column;
	}
	
	public function getDbType(): string
	{
		return strtolower($this->dbSchema()->getType($this->schemaIndex));
	}
	
	public function isDbType(string ...$type): bool
	{
		$columnType = $this->getDbType();
		foreach ($type as $t) {
			$t = strtolower($t);
			if ($t[0] == '/' and preg_match($t, $columnType)) {
				return true;
			}
			if ($t === $columnType) {
				return true;
			}
		}
		
		return false;
	}
	
	public function isNullAllowed(): bool
	{
		return $this->dbSchema()->isNullAllowed($this->schemaIndex);
	}
	
	public function getValueLength()
	{
		return $this->dbSchema()->getLength($this->schemaIndex);
	}
	
	public function getAllowedValues(): array
	{
		return $this->dbSchema()->getAllowedValues($this->schemaIndex);
	}
	
	public function isSigned(): bool
	{
		return $this->dbSchema()->isSigned($this->schemaIndex);
	}
	
	public function getDefaultValue()
	{
		return $this->dbSchema()->getDefaultValue($this->schemaIndex);
	}
	
	public function addColumnFunction(string $function, array $arguments = [])
	{
		$this->columnFunction[] = [$function, $arguments];
	}
	
	public function getColumnFunctions(): array
	{
		return $this->columnFunction;
	}
	
	public function addValueFunction(string $function, array $arguments = [])
	{
		$this->valueFunction[] = [$function, $arguments];
	}
	
	public function getValueFunctions(): array
	{
		return $this->valueFunction;
	}
	
	/**
	 * @param string $msg
	 * @param array  $extraErrorInfo
	 */
	public function alertFix(string $msg, array $extraErrorInfo = [])
	{
		$extraErrorInfo['predicateType'] = $this->predicateType;
		$extraErrorInfo["isNullAllowed"] = $this->isNullAllowed();
		$extraErrorInfo['value']         = $this->getValue();
		$extraErrorInfo['$field']        = $this;
		Poesis::error(Variable::assign(["c" => $this->schemaIndex], $msg), $extraErrorInfo);
	}
	
	public function validate()
	{
		if ($this->editAllowed === null) {
			$this->alertFix("Field(%c%) editAllowed not defined");
		}
		
		if ($this->isPredicateType('')) {
			$this->alertFix("NodeValue type is required");
		}
		$checkValue = $this->getValue();
		
		if (is_object($checkValue)) {
			if (!($checkValue instanceof Field and $checkValue->isPredicateType('compareColumn'))) {
				$this->alertFix("Field(%c%) cannot be object");
			}
		}
		
		
		$checkValue = $this->getValue();
		if ($checkValue === null and !$this->isNullAllowed()) {
			$this->alertFix("Field(%c%) null is not allowed");
		}
		
		//validate enum,set
		if ($this->isDbType('enum', 'set') and !$this->isPredicateType('like,in,rawValue')) {
			$allowedValues = $this->getAllowedValues();
			$error         = null;
			if ($this->isNullAllowed()) {
				$allowedValues[] = null;
			}
			if ($this->isDbType('set')) {
				if (empty($checkValue)) {
					$allowedValues[] = '';
				}
				if (is_string($checkValue) or is_numeric($checkValue)) {
					$checkValue = explode(',', $checkValue);
					foreach ($checkValue as $cv) //set can have multiple items
					{
						if (!in_array($cv, $allowedValues, true)) {
							$error = "value('$checkValue') is not allowed SET column('$this->column') must be one of [" . join(',', $this->getAllowedValues()) . "]";
							break;
						}
					}
				}
				if (!$error and is_array($checkValue)) //change value to to string
				{
					$this->setValue(join(',', $checkValue));
				}
			}
			else {
				if (!in_array($checkValue, $allowedValues, true)) {
					$error = "column('$this->column') value('$checkValue') is not one of allowed SET(" . join(',', $this->getAllowedValues()) . ")";
				}
			}
			if ($error) {
				$extraErrorInfo                  = [];
				$extraErrorInfo["valueType"]     = gettype($checkValue);
				$extraErrorInfo["value"]         = Variable::dump($checkValue);
				$extraErrorInfo["allowedValues"] = $allowedValues;
				$this->alertFix($error, $extraErrorInfo);
			}
		}
	}
	
	//region ######################################### fixers
	
	public function getFixedValue(): string
	{
		$value = $this->getValue();
		
		if ($this->isPredicateType('between,in')) {
			array_walk($value, function (&$item)
			{
				$item = $this->fixValueByType($item);
			});
			if ($this->isPredicateType('between')) {
				$final = join(' AND ', $value);
			}
			else {
				$final = join(',', $value);
			}
		}
		else {
			$final = $this->fixValueByType($value);
		}
		if ($this->valuePrefix !== null) {
			$final = $this->valuePrefix . $final;
		}
		if ($this->valueSuffix !== null) {
			$final .= $this->valueSuffix;
		}
		if ($this->isPredicateType('like')) {
			return "'$final'";
		}
		
		return $final;
	}
	
	private function fixValueByType($value): string
	{
		if ($this->isPredicateType('inDeCrease')) {
			return QueryCompiler::fixColumnName($this->getColumn()) . ' ' . $this->getComparison() . ' ' . $value;
		}
		elseif ($this->isPredicateType('rawValue,null')) {
			return $value;
		}
		elseif ($this->isNullAllowed() and $this->isPredicateType('simpleValue') and $value === null) {
			if ($this->comparison == '!=') {
				$this->comparison = 'IS NOT';
			}
			else {
				$this->comparison = 'IS';
			}
			
			return 'NULL';
		}
		elseif ($this->isNullAllowed() and $this->isPredicateType('in') and $value === null) {
			return 'NULL';
		}
		elseif ($this->isPredicateType('compareColumn')) {
			return QueryCompiler::fixColumnName($value);
		}
		elseif (is_object($value) and $value instanceof Field and $value->isPredicateType('compareColumn')) {
			return QueryCompiler::fixColumnName($value->getValue());
		}
		
		if ($this->isDbType('/int/i')) {
			return $this->fixInt($value);
		}
		elseif ($this->isDbType('float', 'double', 'real', 'decimal')) {
			return $this->fixNumeric($value, $this->getDbType());
		}
		elseif ($this->isDbType('/date/i', '/time/i', 'year')) {
			return $this->fixDateOrTimeType($value);
		}
		
		return $this->escape($value);
		
	}
	
	private function escape(string $value): string
	{
		$value = $this->connection()->escape($value);
		if ($this->isPredicateType('like')) {
			return $value;
		}
		
		return "'$value'";
	}
	
	private function fixInt($value): int
	{
		$type = $this->getDbType();
		if ($this->isDbType('tinyint') and $this->getValueLength() == 1 and is_bool($value)) {
			$value = (int)$value;
		}
		else {
			$typeCastedNumber = (int)$value;
			if ("$typeCastedNumber" != "$value") {
				$extra                      = [];
				$extra['$number']           = Variable::dump($value);
				$extra['$typeCastedNumber'] = Variable::dump($typeCastedNumber);
				$this->alertFix("Field(%c%) value must be correct $type, value($value) was provided", $extra);
			}
			$value = $typeCastedNumber;
		}
		
		$minMax                              = [];
		$minMax['bigint']['signed']['max']   = 9223372036854775807;
		$minMax['bigint']['signed']['min']   = -9223372036854775808;
		$minMax['bigint']['unsigned']['max'] = 18446744073709551615;
		
		$minMax['mediumint']['signed']['max']   = 8388607;
		$minMax['mediumint']['signed']['min']   = -8388608;
		$minMax['mediumint']['unsigned']['max'] = 16777215;
		
		$minMax['smallint']['signed']['max']   = 32767;
		$minMax['smallint']['signed']['min']   = -32768;
		$minMax['smallint']['unsigned']['max'] = 65535;
		
		$minMax['tinyint']['signed']['max']   = 127;
		$minMax['tinyint']['signed']['min']   = -128;
		$minMax['tinyint']['unsigned']['max'] = 255;
		
		$minMax['int']['signed']['max']   = 2147483647;
		$minMax['int']['signed']['min']   = -2147483648;
		$minMax['int']['unsigned']['max'] = 4294967295;
		
		if ($this->isSigned()) {
			$sig = 'SIGNED';
			if ($value > $minMax[$type]['signed']['max'] || $value < $minMax[$type]['signed']['min']) {
				Poesis::addErrorData("givenValue", $value);
				Poesis::addErrorData("givenValueType", gettype($value));
				$this->alertFix("Invalid Field(%c%) value $value for $sig $type, allowed min=" . $minMax[$type]['signed']['min'] . ", allowed max=" . $minMax[$type]['signed']['max']);
			}
		}
		else {
			$sig = 'UNSIGNED';
			if ($value > $minMax[$type]['unsigned']['max'] || $value < 0) {
				Poesis::addErrorData("givenValue", $value);
				Poesis::addErrorData("givenValueType", gettype($value));
				$this->alertFix("Invalid Field(%c%) value $value for $sig $type, allowed min=0, allowed max=" . $minMax[$type]['unsigned']['max']);
			}
		}
		
		return $value;
	}
	
	private function fixNumeric($value, string $dbType)
	{
		$value = str_replace(',', '.', "$value");
		if (!is_numeric($value)) {
			$extra           = [];
			$extra['$value'] = Variable::dump($value);
			$this->alertFix("Field(%c%) value must be correct $dbType, value(%value%) was provided", $extra);
		}
		$value  = str_replace(',', '.', floatval($value));
		$length = $this->getValueLength();
		if ($length !== null) {
			$lengthStr   = $length['d'] . '.' . $length['p'];
			$ex          = explode('.', $value);
			$valueDigits = strlen($ex[0]);
			if ($valueDigits > $length['fd']) {
				$this->alertFix("Field(%c%) value $value is out of range for $dbType($lengthStr) for value $value");
			}
			if ($dbType == 'decimal') {
				$decimalDigits = strlen((isset($ex[1])) ? $ex[1] : 0);
				if ($decimalDigits > $length['p']) {
					$this->alertFix("Field(%c%) precision length $decimalDigits is out of range for $dbType($lengthStr) for value $value");
				}
			}
		}
		
		return $value;
	}
	
	private function fixDateOrTimeType($value): string
	{
		$type       = $this->getDbType();
		$length     = intval($this->getValueLength());
		$defaultNow = ['now', 'now()', 'current_timestamp()', 'current_timestamp(0)', 'current_timestamp', $this->getDefaultValue()];
		$setType    = 'string';
		
		if ($this->isPredicateType('like')) {
			return $this->escape($value);
		}
		
		if ($this->isDbType('year')) {
			if (in_array(strtolower($value), $defaultNow)) {
				return 'YEAR(NOW())';
			}
			if (preg_match('/^[0-9]+$/m', $value)) {
				if ($length == 4) {
					$v = intval($value);
					if (Is::between($v, 0, 69)) {
						$v = $v + 2000;
					}
					elseif (Is::between($v, 70, 99)) {
						$v = $v + 1900;
					}
					if ($v < 1901 or $v > 2155) {
						$this->alertFix("Field(%c%) must be between 1901 AND 2155 ($value) was given");
					}
				}
				else {
					$v = intval($value);
					if (Is::between($v, 2000, 2069)) {
						$v = $v - 2000;
					}
					elseif (Is::between($v, 1970, 1999)) {
						$v = $v - 1900;
					}
					elseif ($v < 0 or $v > 99) {
						$this->alertFix("Field(%c%) must be between 0 AND 99 ($value) was given");
					}
				}
				$rawValue = $v;
			}
			else {
				$time = Date::toTime($value);
				if (!$time) {
					$this->alertFix("Field(%c%) value($value) does not valid as $type");
				}
				else {
					$rawValue = date('Y', $time);
				}
			}
			
			return $rawValue;
		}
		if (in_array(strtolower($value), $defaultNow)) {
			if ($this->isDbType('datetime', 'date')) {
				$rawValue = 'NOW()';
			}
			elseif ($this->isDbType('timestamp')) {
				if ($length > 0) {
					$rawValue = 'CURRENT_TIMESTAMP(' . $length . ')';
				}
				else {
					$rawValue = 'NOW()';
				}
			}
			else {
				$f        = strtoupper($type);
				$rawValue = "$f(NOW())";
			}
			
			return $rawValue;
		}
		
		$timePrec = '';
		if ($length > 0 and !$this->isDbType('date')) {
			$rlen = $length;
			if (preg_match('/\.[0-9]+/m', $value)) {
				preg_match('/\.[0-9]{0,' . $length . '}/m', $value, $matches);
				$timePrec = $matches[0];
				$rlen     = $length - (strlen($timePrec) - 1);
			}
			else {
				$timePrec = '.';
			}
			if ($rlen > 0 and $rlen <= $length) {
				$timePrec .= str_repeat('0', $rlen);
			}
		}
		
		$time = Date::toTime($value);
		if (!$time) {
			$this->alertFix("Field(%c%) value($value) does not valid as $type");
		}
		$format   = ['time' => 'H:i:s', 'date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'timestamp' => 'Y-m-d H:i:s'];
		$rawValue = date($format[$type], $time) . $timePrec;
		
		return $this->escape($rawValue);
	}
	//endregion
}