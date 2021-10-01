<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\orm\Schema;
use Infira\Poesis\Poesis;
use Infira\Utils\Variable;
use Infira\Utils\Is;
use Infira\Utils\Regex;
use Infira\Utils\Date;

class Field
{
	private $editAllowed = null;
	private $column      = '';
	private $finalColumn = '';
	private $value       = Poesis::UNDEFINED;
	private $connectionName;
	
	private $columnFunction   = [];
	private $valueFunction    = [];
	private $predicateType    = '';
	private $valuePrefix      = '';
	private $valueSuffix      = '';
	public  $__finalQueryPart = [];
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	/**
	 * @see https://dev.mysql.com/doc/refman/8.0/en/non-typed-operators.html
	 * @var string
	 */
	private $operator = '=';
	
	public function __construct(string $column = null, $value = Poesis::UNDEFINED)
	{
		$this->column = $column;
		$this->value  = $value;
	}
	
	public function isOperator(): bool
	{
		return false;
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
	public function getValuePrefix(): string
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
	public function getValueSuffix(): string
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
		if (empty($type))
		{
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
		return in_array(Variable::toLower($this->predicateType), Variable::toArray(Variable::toLower($type)));
	}
	
	/**
	 * @return string
	 */
	public function getOperator(): string
	{
		return $this->operator;
	}
	
	/**
	 * @param string $operator
	 */
	public function setLogicalOperator(string $operator): void
	{
		$this->operator = $operator;
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
	
	/**
	 * @param string $column
	 * @param null   $columnForFinalQuery - what is the column name what is puted to final query, if null it will be same as $column
	 */
	public function setColumn(string $column, $columnForFinalQuery = null)
	{
		$this->column      = $column;
		$this->finalColumn = $columnForFinalQuery ?: $column;
	}
	
	public function addColumnFunction(string $function, array $arguments = [])
	{
		$this->columnFunction[] = [$function, $arguments];
	}
	
	/**
	 * @return array[]
	 */
	public function getColumnFunctions(): array
	{
		return $this->columnFunction;
	}
	
	public function addValueFunction(string $function, array $arguments = [])
	{
		$this->valueFunction[] = [$function, $arguments];
	}
	
	/**
	 * @return array[]
	 */
	public function getValueFunctions(): array
	{
		return $this->valueFunction;
	}
	
	public function setSchema(string $schemaClassName)
	{
		$this->Schema = $schemaClassName;
	}
	
	/**
	 * @param string $msg
	 * @param array  $extraErrorInfo
	 */
	public function alertFix(string $msg, array $extraErrorInfo = [])
	{
		$extraErrorInfo['predicateType']   = $this->predicateType;
		$extraErrorInfo["isNullAllowed"]   = $this->Schema::isNullAllowed($this->column);
		$extraErrorInfo['value']           = $this->getValue();
		$extraErrorInfo['queryPartyValue'] = $this->__finalQueryPart;
		$extraErrorInfo['$field']          = $this;
		Poesis::error(Variable::assign(["c" => $this->Schema::getTableName() . "." . $this->column], $msg), $extraErrorInfo);
	}
	
	/**
	 * validate values after added to clause
	 */
	public function validate()
	{
		if ($this->editAllowed === null)
		{
			$this->alertFix("Field(%c%) editAllowed not defined");
		}
		
		if ($this->isPredicateType(''))
		{
			$this->alertFix("NodeValue type is required");
		}
		$columnType = $this->Schema::getType($this->column);
		$checkValue = $this->getValue();
		
		if (is_object($checkValue))
		{
			if (!($checkValue instanceof Field and $checkValue->isPredicateType('compareColumn')))
			{
				$this->alertFix("Field(%c%) cannot be object");
			}
		}
		
		
		$checkValue = $this->getValue();
		if ($checkValue === null and !$this->Schema::isNullAllowed($this->column))
		{
			$this->alertFix("Field(%c%) null is not allowed");
		}
		
		//validate enum,set
		if (in_array($columnType, ['enum', 'set']) and !$this->isPredicateType('like,in,rawValue'))
		{
			$allowedValues = $this->Schema::getAllowedValues($this->column);
			$error         = null;
			if ($this->Schema::isNullAllowed($this->column))
			{
				$allowedValues[] = null;
			}
			if ($columnType == 'set')
			{
				if (empty($checkValue))
				{
					$allowedValues[] = '';
				}
				if (is_string($checkValue) or is_numeric($checkValue))
				{
					$checkValue = explode(',', $checkValue);
					foreach ($checkValue as $cv) //set can have multiple items
					{
						if (!in_array($cv, $allowedValues, true))
						{
							$error = "$this->column value is is not allowed in SET column type";
							break;
						}
					}
				}
				if (!$error and is_array($checkValue)) //change value to to string
				{
					$this->setValue(join(',', $checkValue));
				}
			}
			else
			{
				if (!in_array($checkValue, $allowedValues, true))
				{
					$error = "$this->column value is is not allowed in ENUM column type";
				}
			}
			if ($error)
			{
				$extraErrorInfo                  = [];
				$extraErrorInfo["valueType"]     = gettype($checkValue);
				$extraErrorInfo["value"]         = Variable::dump($checkValue);
				$extraErrorInfo["allowedValues"] = $allowedValues;
				$this->alertFix($error, $extraErrorInfo);
			}
		}
		
		$value = $this->getValue();
		
		if ($this->isPredicateType('between,in'))
		{
			array_walk($value, function (&$item)
			{
				$item = $this->fixValueByType($item);
			});
			$this->__finalQueryPart = ['array', $value];
		}
		else
		{
			$this->__finalQueryPart = $this->fixValueByType($value);
		}
	}
	
	//region ######################################### fixers
	
	private function fixValueByType($value): array
	{
		$type = $this->Schema::getType($this->column);
		
		if ($this->isPredicateType('rawValue'))
		{
			return ['expression', $value];
		}
		elseif ($this->isPredicateType('null'))
		{
			return ['expression', 'NULL'];
		}
		elseif ($this->Schema::isNullAllowed($this->column) and $this->isPredicateType('simpleValue') and $value === null)
		{
			if ($this->operator == '!=')
			{
				$this->operator = 'IS NOT';
			}
			else
			{
				$this->operator = 'IS';
			}
			
			return ['expression', 'NULL'];
		}
		elseif ($this->Schema::isNullAllowed($this->column) and $this->isPredicateType('in') and $value === null)
		{
			return ['expression', 'NULL'];
		}
		elseif ($this->isPredicateType('compareColumn'))
		{
			return ['column', $value];
		}
		elseif (is_object($value) and $value instanceof Field and $value->isPredicateType('compareColumn'))
		{
			return ['column', $value->getValue()];
		}
		
		if (preg_match('/int/i', $type))
		{
			return ['numeric', $this->fixInt($value)];
		}
		elseif (in_array($type, ['float', 'double', 'real', 'decimal']))
		{
			return ['numeric', $this->fixNumeric($value, $type)];
		}
		elseif (preg_match('/date/i', $type) or preg_match('/time/i', $type) or $type == 'year')
		{
			return $this->fixDateOrTimeType($value);
		}
		else
		{
			return ['string', $value];
		}
	}
	
	private function fixInt($value): int
	{
		$isSigned = $this->Schema::isSigned($this->column);
		$type     = $this->Schema::getType($this->column);
		if ($type == 'tinyint' and $this->Schema::getLength($this->column) == 1 and is_bool($value))
		{
			$value = (int)$value;
		}
		else
		{
			$typeCastedNumber = (int)$value;
			if ("$typeCastedNumber" != "$value")
			{
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
		
		
		if ($isSigned == true)
		{
			$sig = 'SIGNED';
			if ($value > $minMax[$type]['signed']['max'] || $value < $minMax[$type]['signed']['min'])
			{
				Poesis::addExtraErrorInfo("givenValue", $value);
				Poesis::addExtraErrorInfo("givenValueType", gettype($value));
				$this->alertFix("Invalid Field(%c%) value $value for $sig $type, allowed min=" . $minMax[$type]['signed']['min'] . ", allowed max=" . $minMax[$type]['signed']['max']);
			}
		}
		else
		{
			$sig = 'UNSIGNED';
			if ($value > $minMax[$type]['unsigned']['max'] || $value < 0)
			{
				Poesis::addExtraErrorInfo("givenValue", $value);
				Poesis::addExtraErrorInfo("givenValueType", gettype($value));
				$this->alertFix("Invalid Field(%c%) value $value for $sig $type, allowed min=0, allowed max=" . $minMax[$type]['unsigned']['max']);
			}
		}
		
		return $value;
	}
	
	private function fixNumeric($value, string $dbType)
	{
		$value = str_replace(',', '.', "$value");
		if (!is_numeric($value))
		{
			$extra           = [];
			$extra['$value'] = Variable::dump($value);
			$this->alertFix("Field(%c%) value must be correct $dbType, value(%value%) was provided", $extra);
		}
		$value  = str_replace(',', '.', floatval($value));
		$length = $this->Schema::getLength($this->column);
		if ($length !== null)
		{
			$lengthStr   = $length['d'] . '.' . $length['p'];
			$ex          = explode('.', $value);
			$valueDigits = strlen($ex[0]);
			if ($valueDigits > $length['fd'])
			{
				$this->alertFix("Field(%c%) value $value is out of range for $dbType($lengthStr) for value $value");
			}
			if ($dbType == 'decimal')
			{
				$decimalDigits = strlen((isset($ex[1])) ? $ex[1] : 0);
				if ($decimalDigits > $length['p'])
				{
					$this->alertFix("Field(%c%) precision length $decimalDigits is out of range for $dbType($lengthStr) for value $value");
				}
			}
		}
		
		return $value;
	}
	
	private function fixDateOrTimeType($value): array
	{
		$type       = $this->Schema::getType($this->column);
		$length     = intval($this->Schema::getLength($this->column));
		$defaultNow = ['now', 'now()', 'current_timestamp()', 'current_timestamp(0)', 'current_timestamp', $this->Schema::getDefaultValue($this->getColumn())];
		$setType    = 'string';
		
		if ($this->isPredicateType('like'))
		{
			return ['string', $value];
		}
		
		if ($type == 'year')
		{
			$setType = 'numeric';
			if (in_array(strtolower($value), $defaultNow))
			{
				$rawValue = 'YEAR(NOW())';
				$setType  = 'expression';
			}
			else
			{
				if (Regex::isMatch('/^[0-9]+$/m', $value))
				{
					if ($length == 4)
					{
						$v = intval($value);
						if (Is::between($v, 0, 69))
						{
							$v = $v + 2000;
						}
						elseif (Is::between($v, 70, 99))
						{
							$v = $v + 1900;
						}
						if ($v < 1901 or $v > 2155)
						{
							$this->alertFix("Field(%c%) must be between 1901 AND 2155 ($value) was given");
						}
					}
					else
					{
						$v = intval($value);
						if (Is::between($v, 2000, 2069))
						{
							$v = $v - 2000;
						}
						elseif (Is::between($v, 1970, 1999))
						{
							$v = $v - 1900;
						}
						elseif ($v < 0 or $v > 99)
						{
							$this->alertFix("Field(%c%) must be between 0 AND 99 ($value) was given");
						}
					}
					$rawValue = $v;
				}
				else
				{
					$time = Date::toTime($value);
					if (!$time)
					{
						$this->alertFix("Field(%c%) value($value) does not valid as $type");
					}
					else
					{
						$rawValue = date('Y', $time);
					}
				}
			}
		}
		else//if (in_array($type, ['time', 'date', 'datetime', 'timestamp']))
		{
			if (in_array(strtolower($value), $defaultNow))
			{
				if (in_array($type, ['datetime', 'date']))
				{
					$rawValue = 'NOW()';
				}
				elseif ($type == 'timestamp')
				{
					if ($length > 0)
					{
						$rawValue = 'CURRENT_TIMESTAMP(' . $length . ')';
					}
					else
					{
						$rawValue = 'NOW()';
					}
				}
				else
				{
					$f        = strtoupper($type);
					$rawValue = "$f(NOW())";
				}
				$setType = 'expression';
			}
			else
			{
				$timePrec = '';
				if ($length > 0 and $type != 'date')
				{
					$rlen = $length;
					if (preg_match('/\.[0-9]+/m', $value))
					{
						$timePrec = Regex::getMatch('/\.[0-9]{0,' . $length . '}/m', $value);
						$rlen     = $length - (strlen($timePrec) - 1);
					}
					else
					{
						$timePrec = '.';
					}
					if ($rlen > 0 and $rlen <= $length)
					{
						$timePrec .= str_repeat('0', $rlen);
					}
				}
				
				$time = Date::toTime($value);
				if (!$time)
				{
					$this->alertFix("Field(%c%) value($value) does not valid as $type");
				}
				$format   = ['time' => 'H:i:s', 'date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'timestamp' => 'Y-m-d H:i:s'];
				$rawValue = date($format[$type], $time) . $timePrec;
			}
		}
		
		return [$setType, $rawValue];
	}
	
	private function fixBit($value): array
	{
		//[\D2-9]+
		if (Regex::isMatch('/[\D2-9]+/', $value))
		{
			$this->alertFix("Field(%c%) must contain  only 1 or 0");
		}
		
		$length = $this->Schema::getLength($this->column);
		if (strlen($value) <= $length)
		{
			$this->alertFix("Field(%c%) is too big, len($length)");
		}
		
		return ['string', $value];
	}
	//endregion
}