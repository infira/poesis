<?php

use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;

require_once "../vendor/autoload.php";
require_once "../vendor/infira/utils/src/Facade.php";

function requireDirFiles(string $path, $recursive = false)
{
	foreach (Dir::getContents($path, [], $recursive) as $file)
	{
		$f = "$path/$file";
		if (is_file($f))
		{
			require_once $f;
		}
	}
}

require_once "../src/dr/DataMethods.php";
require_once "../src/dr/DataCacher.php";
require_once "../src/dr/DataRetrieval.php";
require_once "../src/Connection.php";
require_once "../src/ConnectionManager.php";


Poesis::setDefaultConnection('localhost', 'vagrant', 'parool', 'poesis');

