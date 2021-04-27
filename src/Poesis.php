<?php

namespace Infira\Poesis;

use Infira\Autoloader\Autoloader;

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
	
	private static $voidLogOnTabales = [];
	private static $options          = [];
	
	public static function init()
	{
		Autoloader::init(null);
		if (!Autoloader::exists('PoesisModelColumnExtendor'))
		{
			Autoloader::setPath('PoesisModelColumnExtendor', __DIR__ . '/extendors/modelColumnExtendor.php');
		}
		if (!Autoloader::exists('PoesisDataMethodsExtendor'))
		{
			Autoloader::setPath('PoesisDataMethodsExtendor', __DIR__ . '/extendors/dataMethodsExtendor.php');
		}
		if (!Autoloader::exists('PoesisConnectionExtendor'))
		{
			Autoloader::setPath('PoesisConnectionExtendor', __DIR__ . '/extendors/connectionExtendor.php');
		}
		self::$options['loggerEnabled'] = false;
	}
	
	//region logging
	
	/**
	 * @param string        $logModelName
	 * @param string        $logDataModelName
	 * @param callable|null $logFilter - method to check is logging ok for certain tables. Will be called just before log transaction
	 */
	public static function enableLogger(string $logModelName = '\TDbLog', string $logDataModelName = '\TDbLogData', callable $logFilter = null)
	{
		self::setOption("loggerEnabled", true);
		self::setOption('logModelName', $logModelName);
		self::setOption('logDataModelName', $logDataModelName);
		if (is_callable($logFilter))
		{
			self::setOption('isLoggerOk', $logFilter);
		}
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
	
	public static function getLogDataModel(): string
	{
		return self::getOption('logDataModelName');
	}
	
	public static function isLoggerEnabled(): bool
	{
		return self::getOption("loggerEnabled", false);
	}
	
	public static function voidLogOn(string $table)
	{
		self::$voidLogOnTabales[$table] = true;
	}
	
	public static function isLogEnabled(string $table, array $setClauses, array $whereClauses): bool
	{
		if (!self::isLoggerEnabled())
		{
			return false;
		}
		if (isset(self::$voidLogOnTabales[$table]))
		{
			return false;
		}
		$isLogOk = self::getOption('isLoggerOk', false);
		if (!is_callable($isLogOk))
		{
			return true;
		}
		
		return $isLogOk($table, $setClauses, $whereClauses);
	}
	
	/**
	 * Enable UUID
	 * Its ebable to get afftected
	 * Use
	 * ALTER TABLE `table` ADD `UUID` VARCHAR(36) NULL DEFAULT NULL COMMENT 'Poesis::UUID', ADD UNIQUE `UUID` (`UUID`(36));
	 * DELIMITER ;;
	 * CREATE TRIGGER before_insert_tablename
	 * BEFORE INSERT ON tablename
	 * FOR EACH ROW
	 * BEGIN
	 * IF new.uuid IS NULL THEN
	 * SET new.uuid = uuid();
	 * END IF;
	 * END
	 * ;;
	 */
	public static function enableUUID()
	{
		self::setOption('UUIDEnabled', true);
	}
	
	public static function disableUUID()
	{
		self::setOption('UUIDEnabled', false);
	}
	
	public static function isUUIDEnabled(): bool
	{
		return self::getOption('UUIDEnabled', false) === true;
	}
	
	//endregion
	
	//region transaction IDS
	/**
	 * Enable transaction IDS for each insert,update
	 * Its ebable to get afftected
	 * Use ALTER TABLE `table` ADD `TID` CHAR(32) NULL DEFAULT NULL COMMENT 'Poesis::transactionID', ADD INDEX `transactionID` (`TID`(32)); to add transaction field to table
	 */
	public static function enableTID()
	{
		self::setOption('TIDEnabled', true);
	}
	
	public static function disableTID()
	{
		self::setOption('TIDEnabled', false);
	}
	
	public static function isTIDEnabled(): bool
	{
		return self::getOption('TIDEnabled', false) === true;
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