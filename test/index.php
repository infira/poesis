<?php
require_once "models/ModelShortcut.trait.php";

use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;
use Infira\Utils\Date;
use Infira\Error\Handler;
use Infira\Error\Node;
use Infira\Poesis\PoesisCache;

require_once "../vendor/infira/errorhandler/src/Error.php";
require_once "config.php";

class Db extends ConnectionManager
{
	use ModelShortcut;
}


Poesis::useInfiraErrorHadler();
Poesis::enableLogger(function ()
{
	return new TDbLog();
});

PoesisCache::init();
PoesisCache::setDefaultDriver(\Infira\Cachly\Cachly::RUNTIME_MEMORY);
$config                         = [];
$config['errorLevel']           = -1;
$config['beforeThrow']          = function (Node $Node)
{
	//var_dump($Node->getVars());
};
$config['debugBacktraceOption'] = 0;
$Handler                        = new Handler($config);

try
{
	$checkQuery = function ($query, $correct)
	{
		cleanOutput(true);
		$query   = str_replace(["\n"], '', $query);
		$correct = str_replace(["\n"], '', $correct);
		if ($query != $correct)
		{
			$ei                  = [];
			$ei['actual query']  = $query;
			$ei['correct query'] = $correct;
			Poesis::error("Compile error", $ei);
		}
	};
	
	requireDirFiles("models/");
	
	Prof()->startTimer("starter");
	
	$Db = new TSafSdOrder();
	$Db->Where->ID->in([99999, 'someRandomString']);
	debug(['cachedData' => $Db->select()->cache()->getArray()]);
	exit;
	
	for ($i = 0; $i < 9999; $i++)
	{
		$Db = new TSafSdOrder();
		$Db->Where->ID->in([99999, 'someRandomString']);
		$Db->companyName = "updated name";
		$Db->getUpdateQuery();
		//$Db->update();
	}
	
	
	$Db = new TSafSdOrder();
	$Db->ID->in([99999, 'someRandomString']);
	$checkQuery($Db->getDeleteQuery(), "DELETE FROM `saf_sd_order`  WHERE `ID` IN ('99999','0')");
	
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau")->or()->date('adasd');
	$Db->tableName("ei ei");
	$Db->lastSync->now();
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sync_date`  WHERE ( `tableName` = 'blaau' AND `tableName` = '1970-01-01' ) AND `tableName` = 'ei ei' AND `lastSync`  =  current_timestamp()");
	
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$Db->collect();
	
	$Db->tableName("ehee");
	$Db->lastSync->now();
	$Db->collect();
	$checkQuery($Db->getReplaceQuery(), "REPLACE INTO `saf_sync_date` (`tableName`,`lastSync`) VALUES ('blaau',now()),('ehee',now())");
	
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$Db->Where->lastSync("yesterday");
	$Db->collect();
	
	$Db->tableName("ehee");
	$Db->lastSync->now();
	$Db->Where->lastSync("tomorrow");
	$Db->collect();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `saf_sync_date` SET `tableName` = 'blaau',`lastSync` = now() WHERE `lastSync` = '" . Date::toSqlDateTime('yesterday') . "';UPDATE `saf_sync_date` SET `tableName` = 'ehee',`lastSync` = now() WHERE `lastSync` = '" . Date::toSqlDateTime('tomorrow') . "'");
	
	//Withoud where
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `saf_sync_date` SET `tableName` = 'blaau',`lastSync` = now()");
	
	Db::TSafSyncDate()->tableName("testAutoSave")->delete();
	$Db = new TSafSyncDate();
	$Db->tableName("testAutoSave");
	$Db->lastSync("now");
	$Db->dontNullFields();
	$q = $Db->getSaveQuery();
	$checkQuery($q, "INSERT INTO `saf_sync_date` (`tableName`,`lastSync`) VALUES ('testAutoSave',current_timestamp())");
	Db::query($q);
	$q = $Db->getSaveQuery();
	$checkQuery($q, "UPDATE `saf_sync_date` SET `lastSync` = current_timestamp() WHERE `tableName` = 'testAutoSave'");
	Db::query($q);
	
	$Db = new TSafSyncDate();
	$Db->lastSync("now");
	$q = $Db->getSaveQuery();
	$checkQuery($q, "INSERT INTO `saf_sync_date` (`lastSync`) VALUES (current_timestamp())");
	Prof()->stopTimer("starter");
	
	echo Prof()->dumpTimers();
}
catch (\Infira\Error\Error $e)
{
	echo $e->getMessage();
}
catch (Throwable $e)
{
	echo $Handler->catch($e);
}
