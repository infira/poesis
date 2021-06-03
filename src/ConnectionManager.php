<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataMethods;
use mysqli_result;
use Infira\Utils\Facade;
use Infira\Utils\ClassFarm;

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
class ConnectionManager extends Facade
{
	public static function getInstanceConfig(): array
	{
		return ['name' => 'DefaultPoesisDbConnection', 'constructor' => function ()
		{
			return self::default();
		}];
	}
	
	public static function default(): Connection
	{
		$name   = 'defaultPoesisDbConnection';
		$config = Poesis::getOption('defaultConnection');
		if ($config === null)
		{
			Poesis::error('default connection is unset');
		}
		
		return self::getInstance($name, function () use ($name, $config)
		{
			return new Connection($name, $config['host'], $config['user'], $config['pass'], $config['name'], $config['port'], $config['socket']);
		});
	}
	
	/**
	 * @param string   $name
	 * @param callable $con
	 */
	public static function set(string $name, callable $con)
	{
		ClassFarm::add($name, $con);
	}
	
	/**
	 * @param string $name
	 * @return \Infira\Poesis\Connection
	 */
	public static function get(string $name): Connection
	{
		return self::getInstance($name, function () use ($name)
		{
			Poesis::error("$name connection is unset");
		});
	}
}

?>