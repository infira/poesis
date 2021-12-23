<style>
	correct {
		color: #000000;
		background: #5affa6;
		text-decoration: none;
	}

	wrong {
		color: #000000;
		background: #ffd0d0;
		text-decoration: none;
	}

	line {
		line-height: 20px;
	}

	ins {
		color: green;
		background: #dfd;
		text-decoration: none;
	}

	del {
		color: red;
		background: #fdd;
		text-decoration: none;
	}
</style>
<?php
//ini_set('memory_limit','1G');
require_once '../vendor/autoload.php';
require_once "models/PoesisModelShortcut.php";

use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Utils\Profiler;
use Infira\Poesis\ConnectionManager;
use Infira\Error\Handler;
use Infira\Utils\Regex;
use Infira\Poesis\Connection;
use Infira\Error\ErrorData;

$config                         = [];
$config['errorLevel']           = -1;
$config['beforeThrow']          = function (Node $Node)
{
	//var_dump($Node->getVars());
};
$config['debugBacktraceOption'] = DEBUG_BACKTRACE_IGNORE_ARGS;
$Handler                        = new Handler($config);

try {

	function requireDirFiles(string $path)
	{
		foreach (Dir::getFiles($path) as $file) {
			require_once $file;
		}
	}

	if (!function_exists('Prof')) {
		/**
		 * @param string $name
		 * @return \Infira\Utils\Profiler()
		 */
		function Prof(string $name = "globalProfiler"): Profiler
		{
			if (!isset($GLOBALS["infira_profilers"][$name])) {
				$GLOBALS["infira_profilers"][$name] = new Profiler();
			}

			return $GLOBALS["infira_profilers"][$name];
		}
	}

	Poesis::init();
	require_once "fineDiff.php";
	function checkQuery($query, string $correct)
	{
		$query = str_replace(["\n"], '', $query);
		$ok    = true;
		if ($correct[0] == '/') {
			if (!Regex::isMatch($correct, $query)) {
				$ok = false;
			}
		}
		else {
			$correct = str_replace(["\n"], '', $correct);
			if ($query != $correct) {
				$ok = false;
			}
		}
		if (!$ok) {
			$ei                  = [];
			$ei['correct query'] = "<line><correct>$correct</correct></line>";
			$ei[' actual query'] = "<line><wrong>$query</wrong></line>";
			$ei['         diff'] = '<line>' . FineDiff::renderDiffToHTMLFromOpcodes($correct, FineDiff::getDiffOpcodes($correct, $query)) . '</line>';
			$ei['trace']         = \Infira\Utils\Globals::getTrace();
			Poesis::error("Compile error", $ei);
		}
	}


	ConnectionManager::set('defaultConnection', function ()
	{
		return new Connection('defaultConnection', 'localhost', 'vagrant', 'parool', 'poesis');
	});

	class Db extends ConnectionManager
	{
		use PoesisModelShortcut;

		public static function default(): Connection
		{
			return self::get('defaultConnection');
		}
	}


	requireDirFiles("extensions/");
	requireDirFiles("models/");
	Prof()->startTimer("starter");


	$startMem = memory_get_usage();
	Db::TAllFields()->truncate();
	Poesis::enableTID();
	Db::query("INSERT INTO `all_fields` ( `nullField`, `varchar`, `varchar2`, `year`, `year2`, `time`, `timePrec`, `date`, `timestamp`, `dateTime`, `dateTimePrec`, `tinyText`, `text`, `mediumText`, `longText`, `tinyInt`, `smallInt`, `mediumInt`, `int`, `decimal`, `float`, `double`, `real`, `bit`, `tinyBlob`,
						   `blog`, `mediumBlob`, `longBlog`, `enum`, `set`)
VALUES ( NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL ),
	( NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL );");

	//start of tests
	require_once 'parts/queries.php';
	require_once 'parts/data.php';
	require_once 'parts/reseting.php';
	require_once 'parts/modifedRecord.php';
	require_once 'parts/logging.php';
	require_once 'parts/security.php';
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
catch (\Infira\Poesis\Error $e) {
	foreach ($e->getData() as $k => $v) {
		ErrorData::set($k, $v);
	}
	echo $Handler->catch($e)->getHTMLTable();
}
catch (\Infira\Error\Error $e) {
	echo $e->getHTMLTable();
}
catch (Throwable $e) {
	echo $Handler->catch($e)->getHTMLTable();
}