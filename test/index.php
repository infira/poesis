<?php
require_once "models/PoesisModelShortcut.trait.php";

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
	use PoesisModelShortcut;
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
$config['debugBacktraceOption'] = DEBUG_BACKTRACE_IGNORE_ARGS;
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
			$ei[' actual query'] = $query;
			$ei['correct query'] = $correct;
			Poesis::error("Compile error", $ei);
		}
	};
	
	requireDirFiles("models/");
	
	Prof()->startTimer("starter");
	
	$Db1       = new TSafSyncDate();
	$Db        = new TSafSdOrder();
	$tableName = $Db1->limit(1)->select('tableName')->getFieldValue('tableName');
	$Db->companyName($tableName);
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sd_order` WHERE `companyName` = '$tableName'");
	
	$Db = new TSafSdOrder();
	$Db->ID(1);
	$Db->or()->companyName("test")->companyName->in('testOR');
	$Db->companyName("blaah")->or()->companyName->in('blaahOr');
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sd_order` WHERE `ID` = 1 OR ( `companyName` = 'test' AND `companyName` IN ('testOR') ) AND ( `companyName` = 'blaah' OR `companyName` IN ('blaahOr') )");
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau")->or()->lastSync('now');
	$Db->tableName("ei ei");
	$Db->lastSync->now();
	$Db->lastSync->now();
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sync_date` WHERE ( `tableName` = 'blaau' OR `lastSync` = current_timestamp() ) AND `tableName` = 'ei ei' AND `lastSync` = current_timestamp() AND `lastSync` = current_timestamp()");
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$checkQuery($Db->getInsertQuery(), "INSERT INTO `saf_sync_date` (`tableName`,`lastSync`) VALUES ('blaau',now())");
	
	$Db = new TSafSdOrder();
	$Db->ID(1)->or()->companyName('2');
	$Db->or()->companyName("test")->or()->companyName->in('testOR');
	$Db->companyName("blaah")->or()->companyName->in('blaahOr');
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sd_order` WHERE ( `ID` = 1 OR `companyName` = '2' ) OR ( `companyName` = 'test' OR `companyName` IN ('testOR') ) AND ( `companyName` = 'blaah' OR `companyName` IN ('blaahOr') )");
	
	
	$Db = new TSafSdOrder();
	$Db->ID->in([99999, 'someRandomString'])->or()->in([222]);
	$checkQuery($Db->getDeleteQuery(), "DELETE FROM `saf_sd_order` WHERE ( `ID` IN (99999,0) or `ID` IN (222) )");
	
	
	$Db = new TSafSdOrder();
	$Db->companyName("test2");
	$Db->companyName("test")->or()->companyName->in('testOR');
	$checkQuery($Db->getSelectQuery(), "SELECT  *  FROM `saf_sd_order` WHERE `companyName` = 'test2' AND ( `companyName` = 'test' OR `companyName` IN ('testOR') )");
	
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
	$checkQuery($Db->getUpdateQuery(), "UPDATE `saf_sync_date` SET `tableName` = 'blaau', `lastSync` = now() WHERE `lastSync` = '" . Date::toSqlDateTime('yesterday') . "'");
	
	
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$Db->Where->lastSync("yesterday");
	$Db->collect();
	
	$Db->tableName("ehee");
	$Db->lastSync->now();
	$Db->Where->lastSync("tomorrow");
	$Db->collect();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `saf_sync_date` SET `tableName` = 'blaau', `lastSync` = now() WHERE `lastSync` = '" . Date::toSqlDateTime('yesterday') . "';UPDATE `saf_sync_date` SET `tableName` = 'ehee', `lastSync` = now() WHERE `lastSync` = '" . Date::toSqlDateTime('tomorrow') . "'");
	
	//Withoud where
	$Db = new TSafSyncDate();
	$Db->tableName("blaau");
	$Db->lastSync->now();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `saf_sync_date` SET `tableName` = 'blaau', `lastSync` = now()");
	
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
