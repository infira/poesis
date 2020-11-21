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
	const SKIP      = '__poesis_skip__';
	const BREAK     = '__poesis_break__';
	const CONTINUE  = '__poesis_continue__';
	const NONE      = '__poesis_none__';
	
	private static $voidLogOnTabales = [];
	private static $options          = [];
	
	public static function init()
	{
		require_once __DIR__ . '/Autoloader.php';
		Autoloader::setDataGettersExtendorPath(__DIR__ . '/extendors/PoesisDataMethodsExtendor.php');
		Autoloader::setConnectionExtendorPath(__DIR__ . '/extendors/PoesisConnectionExtendor.php');
		Autoloader::setModelExtendorPath(__DIR__ . '/extendors/PoesisModelExtendor.php');
		spl_autoload_register(['\Infira\Poesis\Autoloader', 'loader'], true, true);
	}
	
	/**
	 * @param callable|string $LogModel - if string new $LogModel() will be created
	 * @param callable|null   $isOk     - method to check is logging ok for certain tables. Will be called just before log transaction
	 */
	public static function enableLogger(callable $LogModel = null, callable $isOk = null)
	{
		self::setOption("loggerEnabled", true);
		if ($LogModel !== null)
		{
			self::setOption('loggerDbModel', $LogModel);
		}
		if (is_callable($isOk))
		{
			self::setOption('isLoggerOk', $isOk);
		}
	}
	
	public static function getLoggerModel(): Model
	{
		$cb = self::getOption('loggerDbModel');
		if (is_string($cb))
		{
			return new $cb();
		}
		
		return $cb();
	}
	
	public static function isLoggerEnabled(): bool
	{
		return self::getOption("loggerEnabled", false);
	}
	
	public static function voidLogOn($table)
	{
		self::$voidLogOnTabales[$table] = true;
	}
	
	public static function isLogOkForTableFields(string $table, array $fields, array $where)
	{
		$isLogOk = self::getOption('isLoggerOk', false);
		if (!is_callable($isLogOk))
		{
			return true;
		}
		
		return $isLogOk($table, $fields, $where);
	}
	
	public static function getModelClassNameFirstLetter(): string
	{
		return self::getOption("modelClassNameFirstLetter", "T");
	}
	
	/**
	 * use Infira error handler for error reporting
	 *
	 * @see - https://github.com/infira/ErrorHandler
	 */
	public static function useInfiraErrorHadler()
	{
		self::setOption('useInfiraErrorHadler', true);
	}
	
	public static function clearErrorExtraInfo()
	{
		if (self::getOption('useInfiraErrorHadler'))
		{
			\Infira\Error\Handler::clearExtraErrorInfo();
		}
	}
	
	public static function addExtraErrorInfo($name, $value = null)
	{
		if (self::getOption('useInfiraErrorHadler'))
		{
			\Infira\Error\Handler::addExtraErrorInfo($name, $value);
		}
	}
	
	public static function error(string $msg, $extra = null)
	{
		if (self::getOption('useInfiraErrorHadler'))
		{
			\Infira\Error\Handler::raise($msg, $extra);
		}
		else
		{
			throw new PoesisError($msg);
		}
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