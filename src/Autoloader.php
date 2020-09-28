<?php

namespace Infira\Poesis;
class Autoloader
{
	public static function loader($className)
	{
		if (in_array($className, ['PoesisDataGettersExtendor', 'PoesisConnectionExtendor', 'PoesisModelExtendor']))
		{
			require_once __DIR__ . '/extendors/' . $className . '.php';
		}
		
		return true;
	}
	
}

?>