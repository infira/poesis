<?php

namespace Infira\Poesis;
class Autoloader
{
	private static $paths = [];
	
	
	public static function loader($className)
	{
		if (in_array($className, array_keys(self::$paths)))
		{
			require_once self::$paths[$className];
		}
		
		return true;
	}
	
	public static function setDataGettersExtendorPath(string $path)
	{
		self::setPath('PoesisDataMethodsExtendor', $path);
	}
	
	
	public static function setConnectionExtendorPath(string $path)
	{
		self::setPath('PoesisConnectionExtendor', $path);
	}
	
	public static function setModelExtendorPath(string $path)
	{
		self::setPath('PoesisModelExtendor', $path);
	}
	
	
	public static function setPath(string $className, string $path)
	{
		if (!file_exists($path))
		{
			Poesis::error("Autoloader class($className) file($path) does not exists");
		}
		self::$paths[$className] = $path;
	}
	
}

?>