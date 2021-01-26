<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataRetrieval;
use mysqli_result;
use Infira\Utils\Facade;

/**
 * Class Db
 * @method static null close()
 * @method static DataRetrieval dr(string $query)
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
	private static $connections = [];
	
	public static function getInstanceConfig(): array
	{
		return ['name' => 'DefaultPoesisDbConnection', 'constructor' => function ()
		{
			return self::default();
		}];
	}
	
	public static function default(): Connection
	{
		return self::get('defaultPoesisDbConnection');
	}
	
	public static function get(string $name): Connection
	{
		if (!isset(self::$connections[$name]))
		{
			$config = Poesis::getOption('defaultConnection');
			if ($config === null)
			{
				Poesis::error('default connection is unset');
			}
			self::$connections[$name] = new Connection($name, $config['host'], $config['user'], $config['pass'], $config['name'], $config['port'], $config['socket']);
		}
		
		return self::$connections[$name];
	}
}