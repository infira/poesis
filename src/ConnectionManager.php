<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataMethods;
use mysqli_result;
use Infira\Poesis\support\Repository;

/**
 * Class Db
 * @method static null close()
 * @method static DataMethods dr(string $query)
 * @method static mysqli_result query(string $query)
 * @method static bool realQuery(string $query)
 * @method static void multiQuery(string $query, callable $callback = null)
 * @method static mixed escape($data, bool $checkArray = false)()
 * @method static void fileQuery(string $fileLocation, array $vars = [])
 * @method static int getLastInsertID()
 * @method static object getLastQueryInfo()
 * @method static void debugLastQuery()
 * @method static bool setVar(string $name, bool $value = false)
 * @method static mixed getVar(string $name)
 */
class ConnectionManager
{
	protected static $connection  = 'defaultConnection';
	private static   $connections = [];
	
	public static function __callStatic(string $method, array $args)
	{
		return static::get(self::$connection)->$method(...$args);
	}
	
	public static function setDbSchema(string $connectionName, string $class) {}
	
	public static function setConfig(string $dbSchemaClass, callable $connector, string $connectionName = 'defaultConnection')
	{
		Repository::__setDbSchemaClass($connectionName, $dbSchemaClass);
		self::$connections[$connectionName] = $connector;
	}
	
	public static function get(string $name): Connection
	{
		if (!self::exists($name)) {
			Poesis::error("connection('$name') is unset");
		}
		
		if (is_callable(self::$connections[$name])) {
			self::$connections[$name] = self::$connections[$name]();
		}
		
		return self::$connections[$name];
	}
	
	public static function exists(string $name): bool
	{
		return isset(self::$connections[$name]);
	}
}
