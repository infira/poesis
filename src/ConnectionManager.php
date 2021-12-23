<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataMethods;
use mysqli_result;

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
	private static $connections = [];
	
	public static function __callStatic(string $method, array $args)
	{
		return static::default()->$method(...$args);
	}
	
	public static function default(): Connection
	{
		Poesis::error('ConnectionManager is not implemented');
	}
	
	/**
	 * @param string              $name
	 * @param Connection|callable $con
	 * @return void
	 */
	public static function set(string $name, $con)
	{
		self::$connections[$name] = $con;
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
	
	public static function getAuto(string $name, callable $con): Connection
	{
		if (!self::exists($name)) {
			self::set($name, $con());
		}
		
		return self::get($name);
	}
	
	public static function exists(string $name): bool
	{
		return isset(self::$connections[$name]);
	}
}
