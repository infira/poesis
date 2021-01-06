<?php

namespace Infira\Poesis\orm\node;

use Infira\Utils\Variable;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Poesis\orm\Model;
use Infira\Utils\Date;
use Infira\Poesis\orm\Schema;
use Infira\Utils\Is;

class ValueNode extends ValueNodeExtender
{
	/**
	 * @var OperatorNode
	 */
	//public  $Op         = null;
	private $fieldName  = "";
	private $function   = "";
	private $md5Value   = false;
	private $fieldLower = false;
	private $addLeftP   = false;
	private $addRightP  = false;
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	
	/**
	 * @var Model
	 */
	public $Model = false;
	
	public function __construct()
	{
		parent::__construct(false, false, true);
		$this->data = "";
	}
	
	public function set(\stdClass $value)
	{
		if (isset($value->md5Value))
		{
			$this->md5Value = $value->md5Value;
		}
		if (isset($value->fieldLower))
		{
			$this->fieldLower = $value->fieldLower;
		}
		
		if (isset($value->addLeftP))
		{
			$this->addLeftP = $value->addLeftP;
		}
		if (isset($value->addRightP))
		{
			$this->addRightP = $value->addRightP;
		}
		$this->data     = $value->value;
		$this->function = strtolower($value->function);
	}
	
	public function ok()
	{
		return (!empty($this->data));
	}
	
	public function isFunction(string $f): bool
	{
		return Variable::toLower($this->function) === Variable::toLower($f);
	}
	
	public function isMD5Value()
	{
		return $this->md5Value;
	}
	
	public function isFieldLower()
	{
		return $this->fieldLower;
	}
	
	public function isLeftP()
	{
		return $this->addLeftP;
	}
	
	public function isRightP()
	{
		return $this->addRightP;
	}
	
	public function getFunction()
	{
		return $this->function;
	}
	
	public function getFieldName()
	{
		return $this->fieldName;
	}
	
	public function setField($field)
	{
		$this->fieldName = $field;
	}
	
	public function setSchema(string $schemaClassName)
	{
		$this->Schema = $schemaClassName;
	}
	
	public function setOperator(OperatorNode $Op)
	{
		$this->Op = $Op;
	}
	
	public function getFixedValue()
	{
		//if ($this->isFunction("between") or $this->isFunction("notbetween") or $this->isFunction("in") or $this->isFunction("notin"))
		if (is_array($this->get()))
		{
			$output = [];
			foreach ($this->get() as $k => $v)
			{
				$output[$k] = $this->fixValue($this->fieldName, $v);
			}
			
			return $output;
		}
		
		return $this->fixValue($this->fieldName, $this->get());
	}
	
	
	//############################################################################################################ Fixers
	
	private function fixValue(string $field, $origValue)
	{
		if ($this->Schema::isRawField($field))
		{
			return $this->regular($field, $origValue);
		}
		$type = $this->Schema::getType($field);
		if (!checkArray($origValue))
		{
			/*
			$lowerValue = strtolower(trim($origValue));
			if (!is_string($origValue) AND !is_numeric($origValue))
			{
				Poesis::addExtraErrorInfo("valueByType value", $origValue);
				Poesis::addExtraErrorInfo("valueByType fixError", ["type" => $type, "field" => $field]);
			}
			*/
			if (preg_match('/int/i', $type))
			{
				return $this->fixInt($field, $origValue);
			}
			elseif (in_array($type, ['decimal']))
			{
				return $this->fixDecimal($field, $origValue);
			}
			elseif (in_array($type, ['timestamp', "time"]) or preg_match('/date/i', $type) or $type == "time")
			{
				return $this->fixDateOrTimeType($field, $origValue);
			}
			elseif (in_array($type, ['float', 'double', 'real']))
			{
				return $this->fixFloat($field, $origValue);
			}
			
			return $this->regular($field, $origValue);
		}
		else
		{
			return eachArray($origValue, function ($key, $value) use (&$field)
			{
				return $this->fixValue($field, $value);
			});
			//Poesis::error("array is not implemeneted");
		}
	}
	
	/**
	 * @param string   $field
	 * @param          $value
	 * @param array    $iatudv - in what value cases is allowed to use default value
	 * @param callable $cb
	 * @return string
	 */
	private final function ccc(string $field, $value, array $iatudv = [], callable $cb)
	{
		if (!$this->Schema::isRawField($field))
		{
			$type         = $this->Schema::getType($field);
			$defaultValue = $this->Schema::getDefaultValue($field);
			$nullAllowed  = $this->Schema::isNullAllowed($field);
			if (is_array($value) or is_object($value))
			{
				$this->alertFix($field, "Field $field value cannot be object/array", ['value' => $value]);
			}
			
			if (($value === null or strtolower($value) == 'null'))
			{
				if (!$nullAllowed)
				{
					$this->alertFix($field, "Field %f% null is not allowed");
				}
				
				return '__fdv_null()';
			}
			elseif (in_array($type, ["date", "datetime", "timestamp", "time", "year"]))
			{
				if (in_array(str_replace(',', '.', $value), $iatudv))
				{
					$length = intval($this->Schema::getLength($field));
					if ($length > 0)
					{
						return "current_timestamp($length)";
					}
					else
					{
						return "current_timestamp()";
					}
				}
				elseif (empty($value) or empty(trim($value)))
				{
					$this->alertFix($field, 'Field(%f%) cannot be empty');
				}
				else
				{
					return $cb();
				}
			}
			elseif (checkArray($iatudv) and in_array($value, $iatudv))
			{
				return $defaultValue;
			}
		}
		else
		{
			return $value;
		}
		
		return $cb();
	}
	
	/**
	 * @param string $field
	 * @param string $msg
	 */
	private final function alertFix($field, $msg)
	{
		Poesis::error(Variable::assign(["f" => $this->Schema::getTableField($field)], $msg));
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @see https://dev.mysql.com/doc/refman/5.7/en/integer-types.html
	 * @return string|int
	 */
	private final function fixInt(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			$r        = intval(trim($value));
			$type     = $this->Schema::getType($field);
			$isSigned = $this->Schema::isSigned($field);
			
			
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
					$this->alertFix($field, "Invalid field %f% value $value for $sig $type, allowed min=" . $minMax[$type]['signed']['min'] . ", allowed max=" . $minMax[$type]['signed']['max']);
				}
			}
			else
			{
				$sig = 'UNSIGNED';
				if ($value > $minMax[$type]['unsigned']['max'] || $value < 0)
				{
					Poesis::addExtraErrorInfo("givenValue", $value);
					Poesis::addExtraErrorInfo("givenValueType", gettype($value));
					$this->alertFix($field, "Invalid field %f% value $value for $sig $type, allowed min=0, allowed max=" . $minMax[$type]['unsigned']['max']);
				}
			}
			
			return $r;
		});
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @see https://dev.mysql.com/doc/refman/5.7/en/floating-point-types.html
	 * @return string|float
	 */
	private final function fixDecimal(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			$length   = $this->Schema::getLength($field);
			$rawValue = floatval(str_replace([',', "'", '"'], ['.', '', ''], trim($value)));
			if ($length !== null)
			{
				$lengthStr     = $length['d'] . '.' . $length['p'];
				$ex            = explode('.', $rawValue);
				$valueDigits   = strlen($ex[0]);
				$decimalDigits = strlen((isset($ex[1])) ? $ex[1] : 0);
				if ($valueDigits > $length['fd'])
				{
					$this->alertFix($field, "Field %f% value $rawValue is out of range for decimal($lengthStr) for value $rawValue");
				}
				if ($decimalDigits > $length['p'])
				{
					$this->alertFix($field, "Field %f% precision length $decimalDigits is out of range for decimal($lengthStr) for value $rawValue");
				}
			}
			
			return str_replace(',', '.', $rawValue); //cause some contries has , instead of .
		});
		
		
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @see https://dev.mysql.com/doc/refman/5.7/en/fixed-point-types.html
	 * @return string|float
	 */
	private final function fixFloat(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			$length   = $this->Schema::getLength($field);
			$rawValue = floatval(str_replace([',', "'", '"'], ['.', '', ''], trim($value)));
			if ($length !== null)
			{
				$lengthStr   = $length['d'] . '.' . $length['p'];
				$ex          = explode('.', $rawValue);
				$valueDigits = strlen($ex[0]);
				if ($valueDigits > $length['fd'])
				{
					$this->alertFix($field, "Field %f% value $rawValue is out of range for float($lengthStr) for value $rawValue");
				}
			}
			
			return str_replace(',', '.', $rawValue); //cause some contries has , instead of .
		});
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @return string
	 */
	private function fixDateOrTimeType(string $field, $value)
	{
		$formats              = [];
		$formats["date"]      = "Y-m-d";
		$formats["datetime"]  = "Y-m-d H:i:s";
		$formats["timestamp"] = "Y-m-d H:i:s";
		$formats["time"]      = "H:i:s";
		
		$type       = $this->Schema::getType($field);
		$length     = intval($this->Schema::getLength($field));
		$dateFormat = $formats[$type];
		
		
		$iatudv             = []; //is allowed to use default value
		$iatudv["datetime"] = ['0000-00-00', '0000.00.00', '1899-12-30 00:00:00'];
		$iatudv["time"]     = ['00:00:00'];
		if ($length > 0)
		{
			$iatudv["datetime"][]  = "0000-00-00 00:00:00." . str_repeat("0", $length);
			$iatudv["timestamp"][] = "0000.00.00 00:00:00." . str_repeat("0", $length);
			$iatudv["time"][]      = "00:00:00." . str_repeat("0", $length);
		}
		else
		{
			
			$iatudv["datetime"][] = '0000-00-00 00:00:00';
			$iatudv["datetime"][] = '0000.00.00 00:00:00';
			$iatudv["time"][]     = '00:00:00';
		}
		$iatudv["timestamp"] = $iatudv["datetime"];
		$iatudv["date"]      = ['0000-00-00', '0000.00.00'];
		
		
		$iatudv = array_merge($iatudv[$type], ['now', 'now()', 'current_timestamp()', 'current_timestamp(0)', 'current_timestamp']);
		
		return $this->ccc($field, $value, $iatudv, function () use (&$field, &$value, &$dateFormat, &$defaultValuesAllowedValues)
		{
			$length = intval($this->Schema::getLength($field));
			$value  = (string)trim($value);
			Poesis::addExtraErrorInfo('$dateFormat', $dateFormat);
			Poesis::addExtraErrorInfo('originalValue', $value);
			if (Regex::isMatch('/^current_timestamp( |)+\([0-' . $length . ']\)$/m', $value))
			{
				return $value;
			}
			$time = strtotime($value);
			if ($time)
			{
				$value = $time;
			}
			elseif (!Is::number($value))
			{
				$this->alertFix($field, "Field %f% doenst valid as time, $value given");
			}
			
			$fValue = Variable::toNumber($value);
			
			$int = $fValue;
			$dec = "";
			if (!is_int($fValue))
			{
				$ex  = explode(",", str_replace(".", ",", $fValue));
				$int = $ex[0];
				$dec = "." . $ex[1];
			}
			if ($int < 0)
			{
				$this->alertFix($field, "Field %f% must be bigger than 0, $value given");
			}
			
			$r = Date::toDate($int, $dateFormat) . $dec;
			
			
			if ($length > 0)
			{
				$d = new DateTime();
				$r .= "." . substr($d->format("vu"), 0, $length);
			}
			
			return "'" . $r . "'";
			
		});
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @return string
	 */
	private final function fixBit(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			//[\D2-9]+
			if (Regex::isMatch('/[\D2-9]+/', $value))
			{
				$this->alertFix($field, "Field %f% must contain  only 1 or 1");
			}
			
			$length = $this->Schema::getLength($field);
			if (strlen($value) <= $length)
			{
				$this->alertFix($field, "Field %f% is too big, len($length)");
			}
			
			return $value;
		});
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @return string
	 */
	private final function fixBool(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			return $value;
		});
	}
	
	/**
	 * @param string $field
	 * @param mixed  $value
	 * @return string
	 */
	private final function regular(string $field, $value)
	{
		return $this->ccc($field, $value, [], function () use (&$field, &$value)
		{
			return "'[MSQL-ESCAPE]" . $value . "[/MSQL-ESCAPE]'";
		});
	}
}

?>