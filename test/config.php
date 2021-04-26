<?php
//ini_set('memory_limit','1G');
require_once '../vendor/autoload.php';
require_once "models/PoesisModelShortcut.trait.php";

use Infira\Utils\Dir;
use Infira\Poesis\Poesis;

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

Poesis::init();
Poesis::setDefaultConnection('localhost', 'vagrant', 'parool', 'poesis');
