<?php
require_once "config.php";

use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;
use Infira\Error\Handler;

require_once "myCustomAbstractModelExtendor.php";

class Db extends ConnectionManager
{
	use PoesisModelShortcut;
}

$config                         = [];
$config['errorLevel']           = -1;
$config['beforeThrow']          = function (Node $Node)
{
	//var_dump($Node->getVars());
};
$config['debugBacktraceOption'] = DEBUG_BACKTRACE_IGNORE_ARGS;
$Handler                        = new Handler($config);

try
{
	requireDirFiles("extensions/");
	requireDirFiles("models/");
	Prof()->startTimer("starter");
	
	$startMem = memory_get_usage();
	require_once 'testQueries.php';
	//require_once 'testData.php';
	//require_once 'testLogging.php';
	$endMem = memory_get_usage();
	
	Prof()->stopTimer("starter");
	
	function convert($size)
	{
		$unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
		
		return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
	}
	
	debug(['memory_get_usage()' => convert($endMem - $startMem)]);
	echo Prof()->dumpTimers();
	exit("tests passed");
}
catch (\Infira\Error\Error $e)
{
	echo $e->getHTMLTable();
}
catch (Throwable $e)
{
	echo $Handler->catch($e)->getHTMLTable();
}