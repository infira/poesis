<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\ConnectionManager;
use Infira\Poesis\orm\Schema;
use Infira\Poesis\Poesis;
use Infira\Utils\Variable;
use Infira\Poesis\QueryCompiler;
use Infira\Utils\Is;
use Infira\Utils\Regex;
use Infira\Utils\Date;

class Field
{
	private $column      = "";
	private $finalColumn = "";
	private $value       = Poesis::UNDEFINED;
	private $connectionName;
	
	private $columnFunction = [];
	private $predicateType  = '';
	private $valuePrefix    = '';
	private $valueSuffix    = '';
	private $finalValue     = '';
	private $queryType      = '';
	
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
	
	public function getFinalValue()
	{
		return $this->finalValue;
	}
	
	public function getFinalValueAt($key)
	{
		return $this->finalValue[$key];
	}
	
	public function getAt($key)
	{
		return $this->value[$key];
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
	public function setOperator(string $operator): void
	{
		$this->operator = $operator;
	}
	
	public function getColumn(): string
	{
		return $this->column;
	}
	
	public function getFinalColumn()
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
		$this->finalColumn = ($columnForFinalQuery) ? $columnForFinalQuery : $column;
	}
	
	public function getColumnForFinalQuery(bool $addQueryFunctions): string
	{
		$ff = QueryCompiler::fixColumn_Table($this->finalColumn);
		if ($this->columnFunction and $addQueryFunctions)
		{
			$column = $ff;
			foreach ($this->columnFunction as $key => $item)
			{
				$function  = strtoupper($item[0]);
				$arguments = $item[1];
				if (checkArray($arguments))
				{
					array_walk($arguments, function (&$item)
					{
						$item = "'" . $item . "'";
					});
					$arguments = ',' . join(',', $arguments);
				}
				else
				{
					$arguments = '';
				}
				$column = "$function($column$arguments)";
			}
			
			return $column;
		}
		else
		{
			return $ff;
		}
	}
	
	public function addColumnsFunction(string $function, array $arguments = [])
	{
		$this->columnFunction[] = [$function, $arguments];
	}
	
	public function setSchema(string $schemaClassName)
	{
		$this->Schema = $schemaClassName;
	}
	
	/**
	 * @param string $msg
	 * @param array  $extraErrorInfo
	 */
	private function alertFix(string $msg, array $extraErrorInfo = [])
	{
		Poesis::error(Variable::assign(["c" => $this->Schema::makeJoinTableName($this->column)], $msg), $extraErrorInfo);
	}
	
	public function validate()
	{
		$columnType = $this->Schema::getType($this->column);
		
		if ($this->isPredicateType(''))
		{
			Poesis::error("NodeValue type is required", ['node' => $this]);
		}
		
		//validate enum,set
		if (in_array($columnType, ['enum', 'set']) and !$this->isPredicateType('notEmpty,empty,like,notlike,in,notIn'))
		{
			
			$checkValue    = $this->getValue();
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
					$allowedValues[] = "";
				}
				if (is_string($checkValue) or is_numeric($checkValue))
				{
					$checkValue = explode(',', $checkValue);
				}
				foreach ($checkValue as $cv) //set can have multiple items
				{
					if (!in_array($cv, $allowedValues, true))
					{
						$error = "$this->column value is is not allowed in SET column type";
						break;
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
				Poesis::clearErrorExtraInfo();
				$extraErrorInfo                  = [];
				$extraErrorInfo["valueType"]     = gettype($checkValue);
				$extraErrorInfo["value"]         = $checkValue;
				$extraErrorInfo["allowedValues"] = $allowedValues;
				$extraErrorInfo["isNullAllowed"] = $this->Schema::isNullAllowed($this->column);
				Poesis::error($error, $extraErrorInfo);
			}
		}
		
		$value = $this->getValue();
		if ($this->isPredicateType('like'))
		{
			$value = $this->getValueSuffix() . $value . $this->getValuePrefix();
		}
		if (is_array($value))
		{
			array_walk($value, function (&$item)
			{
				$fv   = $this->fixValueByType($item);
				$item = $this->addQuotes($this->escape($fv[1]), $fv[0]);
			});
			$this->finalValue = $value;
		}
		else
		{
			$fv = $this->fixValueByType($value);
			if ($this->isPredicateType('rawValue,strictRawValue'))
			{
				$finalValue = str_replace(['[MSQL-ESCAPE]', '[/MSQL-ESCAPE]'], '', $fv[1]);
				
			}
			else
			{
				$fixedValue = $fv[1];
				$finalType  = $fv[0];
				$finalValue = $this->addQuotes($this->escape($fixedValue), $finalType);
			}
			$this->finalValue = $finalValue;
		}
	}
	
	public function validateFinalValue(string $queryType): Field
	{
		if ($this->isPredicateType('like') and $queryType == 'edit')
		{
			$this->alertFix("ModelColumn(%c%) can't use LIKE in edit query", ['value' => $this->getFinalValue()]);
		}
		
		return $this;
	}
	
	//region ######################################### fixers
	
	private function addQuotes($value, string $type)
	{
		if (!is_string($value) and !is_numeric($value) and $type != 'expression')
		{
			Poesis::error('value must string or number', ['value' => $value]);
		}
		
		if (in_array($type, ['expression', 'function', 'numeric']))
		{
			return $value;
		}
		elseif ($type == 'string')
		{
			return "'" . $value . "'";
		}
		else
		{
			Poesis::error('Unknown type', ['type' => $type]);
		}
	}
	
	private function escape($value)
	{
		if (strpos($value, '[MSQL-ESCAPE]') !== false)
		{
			$matches = [];
			preg_match_all('/\[MSQL-ESCAPE\](.*)\[\/MSQL-ESCAPE\]/ms', $value, $matches);
			$value = preg_replace('/\[MSQL-ESCAPE\](.*)\[\/MSQL-ESCAPE\]/ms', ConnectionManager::get($this->connectionName)->escape($matches[1][0]), $value);
		}
		
		return $value;
	}
	
	private function fixValueByType($value): array
	{
		if (is_array($value) or is_object($value))
		{
			$this->alertFix("ModelColumn(%c%) value cannot be object/array", ['value' => $value]);
		}
		if ($value === null)
		{
			if (!$this->Schema::isNullAllowed($this->column))
			{
				$this->alertFix("ModelColumn(%c%) null is not allowed");
			}
			
			return ['expression', 'NULL'];
		}
		if ($this->isPredicateType('strictRawValue,inQuery'))
		{
			return ['expression', $value];
		}
		if ($this->isPredicateType('sqlQuery'))
		{
			return ['expression', $this->valuePrefix . $value . $this->valueSuffix];
		}
		
		/*
		$length = $this->Schema::getLength($this->column);
		if (strpos($this->Schema::getType($this->column), 'char') and strlen($value) > $length)
		{
			$this->alertFix("ModelColumn(%c%) value is out or length($length)", ['value' => $value]);
		}
		elseif (strpos($this->Schema::getType($this->column), 'text') and strlen($value) > $length) //https://stackoverflow.com/questions/6766781/maximum-length-for-mysql-type-text#:~:text=TINYTEXT%20is%20a%20string%20data,commonly%20used%20for%20brief%20articles.
		{
			$this->alertFix("ModelColumn(%c%) value is out or length($length)", ['value' => $value]);
		}
		*/
		
		$fixType = $this->Schema::getFixType($this->column);
		if ($fixType == 'int')
		{
			$fv = $this->fixInt($value);
		}
		elseif ($fixType == 'decimal')
		{
			$fv = $this->fixDecimal($value);
		}
		elseif ($fixType == 'dateTime')
		{
			if ($this->isPredicateType('like') and ($this->valueSuffix == '%' or $this->valuePrefix == '%'))
			{
				$fv = $this->regular($value);
			}
			else
			{
				$fv = $this->fixDateOrTimeType($value);
			}
			
		}
		elseif ($fixType == 'float')
		{
			$fv = $this->fixFloat($value);
		}
		else
		{
			$fv = $this->regular($value);
		}
		
		return $fv;
	}
	
	private function fixInt($value): array
	{
		$fixType = 'numeric';
		if ($this->isPredicateType('like'))
		{
			$fixType = 'string';
			$b       = $e = '';
			if ($value[0] == '%')
			{
				$value = substr($value, 1);
				$b     = '%';
			}
			if (substr($value, -1) == '%')
			{
				$value = substr($value, 0, -1);
				$e     = '%';
			}
		}
		
		if (!Is::number($value))
		{
			$this->alertFix("ModelColumn(%c%) value must be correct integer, value($value) was provided");
		}
		$check = intval($value);
		if ($check != $value)
		{
			$this->alertFix("ModelColumn(%c%) value must be correct integer, value($value) was provided");
		}
		$value    = $check;
		$type     = $this->Schema::getType($this->column);
		$isSigned = $this->Schema::isSigned($this->column);
		
		
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
				$this->alertFix("Invalid ModelColumn(%c%) value $value for $sig $type, allowed min=" . $minMax[$type]['signed']['min'] . ", allowed max=" . $minMax[$type]['signed']['max']);
			}
		}
		else
		{
			$sig = 'UNSIGNED';
			if ($value > $minMax[$type]['unsigned']['max'] || $value < 0)
			{
				Poesis::addExtraErrorInfo("givenValue", $value);
				Poesis::addExtraErrorInfo("givenValueType", gettype($value));
				$this->alertFix("Invalid ModelColumn(%c%) value $value for $sig $type, allowed min=0, allowed max=" . $minMax[$type]['unsigned']['max']);
			}
		}
		if ($fixType == 'numeric')
		{
			return [$fixType, $value];
		}
		else
		{
			return [$fixType, $b . $value . $e];
		}
	}
	
	private function fixDecimal($value): array
	{
		$fixType = 'numeric';
		if ($this->isPredicateType('like'))
		{
			$fixType = 'string';
			$b       = $e = '';
			if ($value[0] == '%')
			{
				$value = substr($value, 1);
				$b     = '%';
			}
			if (substr($value, -1) == '%')
			{
				$value = substr($value, 0, -1);
				$e     = '%';
			}
		}
		
		if (!Is::number($value))
		{
			$this->alertFix("ModelColumn(%c%) value must be correct decimal, value($value) was provided");
		}
		$length = $this->Schema::getLength($this->column);
		$value  = $this->toSqlNumber($value);
		if ($length !== null)
		{
			$lengthStr     = $length['d'] . '.' . $length['p'];
			$ex            = explode('.', $value);
			$valueDigits   = strlen($ex[0]);
			$decimalDigits = strlen((isset($ex[1])) ? $ex[1] : 0);
			if ($valueDigits > $length['fd'])
			{
				$this->alertFix("ModelColumn(%c%) value $value is out of range for decimal($lengthStr) for value $value");
			}
			if ($decimalDigits > $length['p'])
			{
				$this->alertFix("ModelColumn(%c%) precision length $decimalDigits is out of range for decimal($lengthStr) for value $value");
			}
		}
		
		if ($fixType == 'numeric')
		{
			return [$fixType, $this->toSqlNumber($value)];
		}
		else
		{
			return [$fixType, $b . $this->toSqlNumber($value) . $e];
		}
	}
	
	private function fixFloat($value): array
	{
		$fixType = 'numeric';
		if ($this->isPredicateType('like'))
		{
			$fixType = 'string';
			$b       = $e = '';
			if ($value[0] == '%')
			{
				$value = substr($value, 1);
				$b     = '%';
			}
			if (substr($value, -1) == '%')
			{
				$value = substr($value, 0, -1);
				$e     = '%';
			}
		}
		
		if (!Is::number($value))
		{
			$this->alertFix("ModelColumn(%c%) value must be correct float, value($value) was provided");
		}
		$length = $this->Schema::getLength($this->column);
		$value  = $this->toSqlNumber($value);
		if ($length !== null)
		{
			$lengthStr   = $length['d'] . '.' . $length['p'];
			$ex          = explode('.', $value);
			$valueDigits = strlen($ex[0]);
			if ($valueDigits > $length['fd'])
			{
				$this->alertFix("ModelColumn(%c%) value $value is out of range for float($lengthStr) for value $value");
			}
		}
		
		if ($fixType == 'numeric')
		{
			return [$fixType, $this->toSqlNumber($value)];
		}
		else
		{
			return [$fixType, $b . $this->toSqlNumber($value) . $e];
		}
	}
	
	private function fixDateOrTimeType($value): array
	{
		$type       = $this->Schema::getType($this->column);
		$length     = intval($this->Schema::getLength($this->column));
		$defaultNow = ['now', 'now()', 'current_timestamp()', 'current_timestamp(0)', 'current_timestamp'];
		$setType    = 'string';
		
		$addDefaultTimePrecision = false;
		
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
				if ($length == 4)
				{
					if ($value === '0000')
					{
						$setType = 'string';
						$v       = '0000';
					}
					else
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
						if ($v <= 1901 or $v >= 2155)
						{
							$this->alertFix("ModelColumn(%c%) must be between 1901 AND 2155 ($value) was given");
						}
					}
				}
				else
				{
					if ($value === '00')
					{
						$setType = 'string';
						$v       = '00';
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
						elseif ($v <= 0 or $v >= 99)
						{
							$this->alertFix("ModelColumn(%c%) must be between 9 AND 99 ($value) was given");
						}
					}
				}
				$rawValue = $v;
			}
		}
		else//if (in_array($type, ['time', 'date', 'datetime', 'timestamp']))
		{
			if (in_array(strtolower($value), $defaultNow))
			{
				if (in_array($type, ['timestamp', 'datetime']))
				{
					$rawValue = 'NOW()';
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
				$of = ['time' => ['len' => 8, 'com' => '00:00:00'], 'date' => ['len' => 10, 'com' => '0000:00:00'], 'datetime' => ['len' => 19, 'com' => '0000:00:00 00:00:00'], 'timestamp' => ['len' => 19, 'com' => '0000:00:00 00:00:00']];
				if (substr($value, 0, $of[$type]['len']) == $of[$type]['com'])
				{
					$rawValue = $value;
				}
				elseif (substr($value, 0, 2) == '0')
				{
					$this->alertFix("ModelColumn(%c%) value($value) does not valid as $type");
				}
				else
				{
					$timePrec = '';
					if (preg_match('/\.[0-9]{0,6}$/m', $value) and $length > 0 and $type != 'date')
					{
						$timePrec = Regex::getMatch('/\.[0-9]{0,6}$/m', $value);
						$value    = str_replace($timePrec, '', $value);
					}
					
					$time = Date::toTime($value);
					if ($time)
					{
						$format   = ['time' => 'H:i:s', 'date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'timestamp' => 'Y-m-d H:i:s'];
						$rawValue = date($format[$type], $time) . $timePrec;
					}
					else
					{
						$this->alertFix("ModelColumn(%c%) value($value) does not valid as $type");
					}
				}
			}
		}
		
		if ($addDefaultTimePrecision and $length > 0)
		{
			$d        = new \DateTime();
			$rawValue .= "." . substr($d->format("vu"), 0, $length);
		}
		
		if ($this->isPredicateType('like'))
		{
			if ($setType == 'expression')
			{
				$this->alertFix("ModelColumn(%c%) cant use LIKE in expression value");
			}
			$setType = 'string';
		}
		
		if (!isset($timePrec))
		{
			$timePrec = null;
		}
		
		return [$setType, $rawValue];
	}
	
	/*
	private function isCorrectDateTypeValue($value, array $check = null)
	{
		if (is_string($value) and preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]\.[0-9]{0,6}$/m', $value) and (in_array('timestamp', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/m', $value) and (in_array('datetime', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (?:2[0-3]|[01][0-9]):[0-5][0-9]$/m', $value) and (in_array('datetime', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (?:2[0-3]|[01][0-9])$/m', $value) and (in_array('datetime', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/m', $value) and (in_array('date', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]\.[0-9]{0,6}$/m', $value) and (in_array('time', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/m', $value) and (in_array('time', $check) or $check === null))
		{
			return true;
		}
		elseif (is_string($value) and preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/m', $value) and (in_array('time', $check) or $check === null))
		{
			return true;
		}
		
		return false;
	}
	*/
	
	private function fixBit($value)
	{
		//[\D2-9]+
		if (Regex::isMatch('/[\D2-9]+/', $value))
		{
			$this->alertFix("ModelColumn(%c%) must contain  only 1 or 0");
		}
		
		$length = $this->Schema::getLength($this->column);
		if (strlen($value) <= $length)
		{
			$this->alertFix("ModelColumn(%c%) is too big, len($length)");
		}
		
		return ['string', $value];
	}
	
	private function regular($value): array
	{
		return ['string', "[MSQL-ESCAPE]" . $value . "[/MSQL-ESCAPE]"];
	}
	
	private function toSqlNumber($value)
	{
		return str_replace(",", ".", Variable::toNumber($value));
	}
	
	//endregion
}

?>