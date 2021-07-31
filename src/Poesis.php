<?php

namespace Infira\Poesis;

/**
 * Makes a new connection with mysqli
 *
 * @package Infira\Poesis
 */
class Poesis
{
	const UNDEFINED = '__poesis_undefined__';
	const BREAK     = '__poesis_break__';
	const CONTINUE  = '__poesis_continue__';
	const NONE      = '__poesis_none__';
	const VOID      = '__poesis_void__';
	
	private static $options          = [
		'loggerEnabled'       => false,
		'logModelName'        => null,
		'logUserID'           => 0,
		'TIDEnabled'          => false,
		'TIDColumnName'       => 'TID',
		'defaultConnection'   => null,
		'queryHistoryEnabled' => false,
	];
	
	public static function init(array $options = [])
	{
		$default                        = [];
		$default['loggerEnabled']       = ['boolean', false];
		$default['queryHistoryEnabled'] = ['boolean', false];
		$default['logModelName']        = ['string', true];   //should be loggerModel
		$default['logUserID']           = ['integer', false]; //should be loggerUserID
		$default['TIDEnabled']          = ['boolean', false]; //bool
		$default['TIDColumnName']       = ['string', false];  //bool
		$default['defaultConnection']   = [null, false];
		foreach ($default as $k => $conf)
		{
			if (array_key_exists($k, $options))
			{
				$value = $options[$k];
				$type  = $conf[0];
				if ($type !== null)
				{
					$isNullable = $conf[2] ?? false;
					if ($type == 'boolean' and ($type != gettype($value) or ($isNullable and !is_null($value))))
					{
						self::error("option $k must be boolean, $type was given");
					}
					elseif ($type == 'integer' and ($type != gettype($value) or ($isNullable and !is_null($value))))
					{
						self::error("option $k must be integer, $type was given");
					}
					elseif ($type == 'string' and ($type != gettype($value) or ($isNullable and !is_null($value))))
					{
						self::error("option $k must be string, $type was given");
					}
				}
			}
			else
			{
				$value = self::$options[$k];
			}
			self::$options[$k] = $value;
		}
	}
	
	//region query history
	public static function toggleQueryHistory(bool $bol)
	{
		self::setOption("queryHistoryEnabled", $bol);
	}
	
	public static function isQueryHistoryEnabled(): bool
	{
		return self::getOption("queryHistoryEnabled", false);
	}
	//endregion
	
	//region logging
	
	/**
	 * @param string        $logModelName
	 * @param callable|null $logFilter - method to check is logging ok for certain tables. Will be called just before log transaction
	 */
	public static function enableLogger(string $logModelName = '\TDbLog')
	{
		self::setOption("loggerEnabled", true);
		self::setOption('logModelName', $logModelName);
	}
	
	public static function toggleLogger(bool $bol)
	{
		self::setOption("loggerEnabled", $bol);
	}
	
	public static function setLogUserID(int $ID)
	{
		self::setOption('logUserID', $ID);
	}
	
	public static function getLogUserID(): int
	{
		if (!self::optionExists('logUserID'))
		{
			return 0;
		}
		
		return self::getOption('logUserID');
	}
	
	public static function getLogModel(): string
	{
		return self::getOption('logModelName');
	}
	
	public static function isLoggerEnabled(): bool
	{
		return self::getOption("loggerEnabled", false);
	}
	
	public static function voidLogOn(string $table)
	{
		alert("voidLogOn is depreacated, use generator to ste flags to models which to void log");
	}
	//endregion
	
	//region transaction IDS
	/**
	 * Enable transaction IDS for each insert,update
	 * Its ebable to get afftected
	 * Use ALTER TABLE `table` ADD `TID` CHAR(32) NULL DEFAULT NULL COMMENT 'Poesis::transactionID', ADD INDEX `transactionID` (`TID`(32)); to add transaction field to table
	 */
	public static function enableTID(string $TIDCOlumnName = 'TID')
	{
		self::setOption('TIDEnabled', true);
		self::setOption('TIDColumnName', $TIDCOlumnName);
	}
	
	public static function disableTID()
	{
		self::setOption('TIDEnabled', false);
	}
	
	public static function isTIDEnabled(): bool
	{
		return self::getOption('TIDEnabled', false) === true;
	}
	
	public static function getTIDColumnName(): string
	{
		return self::getOption('TIDColumnName', null);
	}
	
	//endregion
	
	public static function clearErrorExtraInfo()
	{
		\Infira\Error\Handler::clearExtraErrorInfo();
	}
	
	public static function addExtraErrorInfo($name, $value = null)
	{
		\Infira\Error\Handler::addExtraErrorInfo($name, $value);
	}
	
	public static function error(string $msg, $extra = null)
	{
		\Infira\Error\Handler::raise($msg, $extra);
	}
	
	/**
	 * @param string      $host   - host
	 * @param string      $user   - username
	 * @param string      $pass   - password
	 * @param string      $name   - database name
	 * @param int|null    $port   - if null default port will be used
	 * @param string|null $socket - if null default socket will be used
	 */
	public static function setDefaultConnection(string $host, string $user, string $pass, string $name, int $port = null, string $socket = null)
	{
		$opt           = [];
		$opt['host']   = $host;
		$opt['user']   = $user;
		$opt['pass']   = $pass;
		$opt['name']   = $name;
		$opt['port']   = $port;
		$opt['socket'] = $socket;
		self::setOption("defaultConnection", $opt);
	}
	
	public static function getOption(string $name, $valueOnNotFound = null)
	{
		if (!self::optionExists($name))
		{
			return $valueOnNotFound;
		}
		
		return self::$options[$name];
	}
	
	//#################################################################### Priv methods
	private static function optionExists(string $name): bool
	{
		return array_key_exists($name, self::$options);
	}
	
	private static function setOption(string $name, $value = self::UNDEFINED)
	{
		if ($value !== self::UNDEFINED)
		{
			self::$options[$name] = $value;
		}
	}
}

?>