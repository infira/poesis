<?php
//ini_set('memory_limit','1G');
require_once '../vendor/autoload.php';
require_once "models/PoesisModelShortcut.trait.php";

use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Utils\Profiler;

function requireDirFiles(string $path)
{
	foreach (Dir::getFiles($path) as $file)
	{
		require_once $file;
	}
}

if (!function_exists('Prof'))
{
	/**
	 * @param string $name
	 * @return \Infira\Utils\Profiler()
	 */
	function Prof(string $name = "globalProfiler"): Profiler
	{
		if (!isset($GLOBALS["infira_profilers"][$name]))
		{
			$GLOBALS["infira_profilers"][$name] = new Profiler();
		}
		
		return $GLOBALS["infira_profilers"][$name];
	}
}

Poesis::init();
Poesis::setDefaultConnection('localhost', 'vagrant', 'parool', 'poesis');
