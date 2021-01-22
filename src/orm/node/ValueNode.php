<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\Schema;
use Infira\Poesis\orm\QueryCompiler;
use Infira\Utils\Variable;
use Infira\Utils\Regex;
use Infira\Utils\Date;
use Infira\Utils\Is;

class ValueNode extends ValueNodeExtender
{
	private $field         = "";
	private $fieldFunction = [];
	private $function      = "";
	private $type          = '';
	private $valuePrefix   = '';
	private $valueSuffix   = '';
	private $finalType     = '';
	private $finalValue    = '';
	
	/**
	 * @see https://dev.mysql.com/doc/refman/8.0/en/non-typed-operators.html
	 * @var string
	 */
	private $operator = '=';
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	public function __construct()
	{
		parent::__construct(false, true);
		$this->data = "";
	}
	
	public function set($value)
	{
		$this->data = $value;
	}
	
	public function getFinalValue()
	{
		return $this->finalValue;
	}
	
	public final function getFinalValueAt($key)
	{
		return $this->finalValue[$key];
	}
	
	public final function getAt($key)
	{
		return $this->data[$key];
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
	
	
	public function setType(string $type)
	{
		if (empty($type))
		{
			Poesis::error('type can\'t be empty');
		}
		$this->type = $type;
	}
	
	public function setFinalType(string $type)
	{
		if (empty($type))
		{
			Poesis::error('type can\'t be empty');
		}
		$this->finalType = $type;
	}
	
	/**
	 * @return string
	 */
	public function getFinalType(): string
	{
		return $this->finalType;
	}
	
	
	public function setFinalValue($value)
	{
		$this->finalValue = $value;
	}
	
	public function isType(string $type): bool
	{
		return in_array(Variable::toLower($this->type), Variable::toArray(Variable::toLower($type)));
	}
	
	public function ok()
	{
		return (!empty($this->data));
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
	
	public function isFunction(string $function): bool
	{
		return in_array(Variable::toLower($this->function), Variable::toArray(Variable::toLower($function)));
	}
	
	public function getField()
	{
		return $this->field;
	}
	
	public function setField(string $field)
	{
		$this->field = $field;
	}
	
	public function getQuotedField()
	{
		return QueryCompiler::fixField($this->field);
	}
	
	public function getFieldNameWithFunction()
	{
		$ff = $this->getQuotedField();
		if ($this->fieldFunction)
		{
			$field = $ff;
			foreach ($this->fieldFunction as $key => $item)
			{
				$function  = strtoupper($item[0]);
				$arguments = $item[1];
				if (checkArray($arguments))
				{
					array_walk($arguments, function (&$item)
					{
						$item = QueryCompiler::getQuotedValue(stripslashes($item), 'string');
					});
					$arguments = ',' . join(',', $arguments);
				}
				else
				{
					$arguments = '';
				}
				$field = "$function($field$arguments)";
			}
			
			return $field;
		}
		else
		{
			return $ff;
		}
	}
	
	public function addFieldFunction(string $function, array $arguments = [])
	{
		$this->fieldFunction[] = [$function, $arguments];
	}
	
	public function setSchema(string $schemaClassName)
	{
		$this->Schema = $schemaClassName;
	}
	
	/**
	 * @param string $msg
	 */
	private final function alertFix($msg, array $extraErrorInfo = [])
	{
		if ($extraErrorInfo)
		{
			foreach ($extraErrorInfo as $name => $value)
			{
				Poesis::addExtraErrorInfo($name, $value);
			}
		}
		Poesis::error(Variable::assign(["f" => $this->Schema::getTableField($this->field)], $msg));
	}
	
	public function fixValue($value)
	{
		if (is_array($value) or is_object($value))
		{
			$this->alertFix("Field(%f%) value cannot be object/array", ['value' => $value]);
		}
		
		/*
		if ($value === null)
		{
			if (!$this->Schema::isNullAllowed($this->field))
			{
				$this->alertFix("Field(%f%) null is not allowed");
			}
			$this->setFinalValue('NULL');
			$this->setFinalType('expression');
			$this->setOperator('IS NULL');
			$this->setType('rawValue');
			
			return;
		}
		else
		*/
		if ($this->Schema::isRawField($this->field))
		{
			$fv = $this->raw($value);
			$this->setType('rawValue');
		}
		else
		{
			$fv = $this->fixValueByType($value);
		}
		
		$this->setFinalType($fv[0]);
		addExtraErrorInfo('$fv[1]', $fv[1]);
		$this->setFinalValue($fv[1]);
	}
	
	public function fixValueByType($value)
	{
		if (is_array($value) or is_object($value))
		{
			$this->alertFix("Field(%f%) value cannot be object/array", ['value' => $value]);
		}
		
		if ($value === null)
		{
			if (!$this->Schema::isNullAllowed($this->field))
			{
				$this->alertFix("Field(%f%) null is not allowed");
			}
			
			return ['expression', 'NULL'];
		}
		
		/*
		$length = $this->Schema::getLength($this->field);
		if (strpos($this->Schema::getType($this->field), 'char') and strlen($value) > $length)
		{
			$this->alertFix("Field(%f%) value is out or length($length)", ['value' => $value]);
		}
		elseif (strpos($this->Schema::getType($this->field), 'text') and strlen($value) > $length) //https://stackoverflow.com/questions/6766781/maximum-length-for-mysql-type-text#:~:text=TINYTEXT%20is%20a%20string%20data,commonly%20used%20for%20brief%20articles.
		{
			$this->alertFix("Field(%f%) value is out or length($length)", ['value' => $value]);
		}
		*/
		
		$fixType = $this->Schema::getFixType($this->field);
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
			$fv = $this->fixDateOrTimeType($value);
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
	
	
	//############################################################################################################ Fixers
	
	private final function fixInt($value): array
	{
		if (!Is::number($value))
		{
			$this->alertFix("Field(%f%) value must be correct integer, value($value) was provided");
		}
		$check = intval($value);
		if ($check != $value)
		{
			$this->alertFix("Field(%f%) value must be correct integer, value($value) was provided");
		}
		$value    = $check;
		$type     = $this->Schema::getType($this->field);
		$isSigned = $this->Schema::isSigned($this->field);
		
		
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
				$this->alertFix("Invalid Field(%f%) value $value for $sig $type, allowed min=" . $minMax[$type]['signed']['min'] . ", allowed max=" . $minMax[$type]['signed']['max']);
			}
		}
		else
		{
			$sig = 'UNSIGNED';
			if ($value > $minMax[$type]['unsigned']['max'] || $value < 0)
			{
				Poesis::addExtraErrorInfo("givenValue", $value);
				Poesis::addExtraErrorInfo("givenValueType", gettype($value));
				$this->alertFix("Invalid Field(%f%) value $value for $sig $type, allowed min=0, allowed max=" . $minMax[$type]['unsigned']['max']);
			}
		}
		
		return ['numeric', $value];
	}
	
	private final function fixDecimal($value): array
	{
		if (!Is::number($value))
		{
			$this->alertFix("Field(%f%) value must be correct decimal, value($value) was provided");
		}
		$length = $this->Schema::getLength($this->field);
		$value  = $this->toSqlNumber($value);
		if ($length !== null)
		{
			$lengthStr     = $length['d'] . '.' . $length['p'];
			$ex            = explode('.', $value);
			$valueDigits   = strlen($ex[0]);
			$decimalDigits = strlen((isset($ex[1])) ? $ex[1] : 0);
			if ($valueDigits > $length['fd'])
			{
				$this->alertFix("Field(%f%) value $value is out of range for decimal($lengthStr) for value $value");
			}
			if ($decimalDigits > $length['p'])
			{
				$this->alertFix("Field(%f%) precision length $decimalDigits is out of range for decimal($lengthStr) for value $value");
			}
		}
		
		return ['numeric', str_replace(',', '.', $value)]; //cause some contries has , instead of .
	}
	
	private final function fixFloat($value): array
	{
		if (!Is::number($value))
		{
			$this->alertFix("Field(%f%) value must be correct float, value($value) was provided");
		}
		$length = $this->Schema::getLength($this->field);
		$value  = $this->toSqlNumber($value);
		if ($length !== null)
		{
			$lengthStr   = $length['d'] . '.' . $length['p'];
			$ex          = explode('.', $value);
			$valueDigits = strlen($ex[0]);
			if ($valueDigits > $length['fd'])
			{
				$this->alertFix("Field(%f%) value $value is out of range for float($lengthStr) for value $value");
			}
		}
		
		return ['numeric', str_replace(',', '.', $value)]; //cause some contries has , instead of .
	}
	
	private final function fixDateOrTimeType($value): array
	{
		$type       = $this->Schema::getType($this->field);
		$length     = intval($this->Schema::getLength($this->field));
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
							$this->alertFix("Field(%f%) must be between 1901 AND 2155 ($value) was given");
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
							$this->alertFix("Field(%f%) must be between 9 AND 99 ($value) was given");
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
				if ($type == 'timestamp')
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
					$this->alertFix("Field(%f%) value($value) does not valid as $type");
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
						$this->alertFix("Field(%f%) value($value) does not valid as $type");
					}
				}
			}
		}
		
		if ($addDefaultTimePrecision and $length > 0)
		{
			$d        = new \DateTime();
			$rawValue .= "." . substr($d->format("vu"), 0, $length);
		}
		
		if ($this->isType('like'))
		{
			if ($setType == 'expression')
			{
				$this->alertFix("Field(%f%) cant use LIKE in expression value");
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
	
	private final function fixBit($value)
	{
		//[\D2-9]+
		if (Regex::isMatch('/[\D2-9]+/', $value))
		{
			$this->alertFix("Field(%f%) must contain  only 1 or 0");
		}
		
		$length = $this->Schema::getLength($this->field);
		if (strlen($value) <= $length)
		{
			$this->alertFix("Field(%f%) is too big, len($length)");
		}
		
		return ['string', $value];
	}
	
	private final function regular($value)
	{
		return ['string', "[MSQL-ESCAPE]" . $value . "[/MSQL-ESCAPE]"];
	}
	
	private final function raw($value)
	{
		if (Is::number($value))
		{
			$ype = 'numeric';
		}
		else
		{
			$ype = 'string';
		}
		
		return [$ype, $value];
	}
	
	private final function toSqlNumber($value)
	{
		return str_replace(",", ".", Variable::toNumber($value));
	}
}

?>