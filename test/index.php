<?php
require_once "config.php";

use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;
use Infira\Utils\Date;
use Infira\Error\Handler;
use Infira\Error\Node;
use Infira\Poesis\Cache;

require_once "myCustomAbstractModelExtendor.php";

class testFetchObjectClass
{
	public function __construct($p = [])
	{
	}
}


class Db extends ConnectionManager
{
	use PoesisModelShortcut;
}


Poesis::enableLogger(function ()
{
	return new TDbLog();
});

Cache::init();
Cache::setDefaultDriver(\Infira\Cachly\Cachly::RUNTIME_MEMORY);

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
	
	
	$dup = new TAllFieldsDup();
	$dup->addRowParser(function ($row)
	{
		debug("parser 1");
		
		return $row;
	});
	$dr = $dup->select();
	$dr->addRowParser(function ($row)
	{
		debug("parser 2");
		
		return $row;
	});
	$std = $dr->getObject();
	if (!is_object($std))
	{
		Poesis::error("Should be object", $std);
	}
	if (get_class($std) != 'stdClass')
	{
		Poesis::error("Should be stdClass", $std);
	}
	
	$testFetchObjectClass = $dr->seek(0)->getObject('testFetchObjectClass');
	if (!is_object($testFetchObjectClass))
	{
		Poesis::error("Should be object", $testFetchObjectClass);
	}
	if (get_class($testFetchObjectClass) != 'testFetchObjectClass')
	{
		Poesis::error("Should be testFetchObjectClass", $testFetchObjectClass);
	}
	
	$Db = new TAllFields();
	$checkQuery($Db->getSelectQuery("ID,null as nullField,'' as emptyField, false as boolField"), "SELECT `ID`,null AS `nullField`,'' AS `emptyField`,false AS `boolField` FROM `all_fields`");
	
	
	$Db = new TAllFields();
	$Db->int->query($dup->limit(1)->getSelectQuery("ID"));
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = (SELECT `ID` FROM `all_fields_dup` LIMIT 1)");
	
	$Db = new TAllFields();
	$Db->raw(" `varchar` LIKE 'blaah'");
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` LIKE 'blaah'");
	
	$Db = new TAllFields();
	$Db->dateTime->sqlFunction('DATE_FORMAT', ['%d.%m.%Y %H:%m:%s'])->like('%09.01.2021 15:0%');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s') LIKE '%09.01.2021 15:0%'");
	
	$Db = new TAllFields();
	$Db->dateTime->like('125%');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `dateTime` LIKE '125%'");
	
	$Db = new TAllFields();
	$Db->int->like('125%');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `int` LIKE '125%'");
	
	
	$Db = new TAllFields();
	$Db->dateTime->now('<')->or()->now('>');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `dateTime` < NOW() OR `dateTime` > NOW() )");
	
	$Db = new TAllFields();
	$Db->varchar->notEmpty();
	$Db->varchar->isEmpty();
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE TRIM(IFNULL(`varchar`,'')) != '' AND TRIM(IFNULL(`varchar`,'')) = ''");
	
	$Db = new TAllFields();
	$Db->varchar('varchar');
	$Db->varchar(123);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'varchar' AND `varchar` = '123'");
	
	$Db = new TAllFields();
	$Db->varchar->in(['varchar', 123, null]);
	$Db->varchar->notIn(['varchar', 123, null]);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IN ('varchar','123',NULL) AND `varchar` NOT IN ('varchar','123',NULL)");
	
	$Db = new TAllFields();
	$Db->varchar->variable('SELECT \'ID FROM table');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = @SELECTIDFROMtable");
	
	$Db = new TAllFields();
	$Db->varchar->not('varchar');
	$Db->varchar->not(123);
	$Db->varchar->not(null);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` != 'varchar' AND `varchar` != '123' AND `varchar` IS NOT NULL");
	
	$Db = new TAllFields();
	$Db->varchar->column('varchar2');
	$Db->varchar->notColumn('varchar2');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = `varchar2` AND `varchar` != `varchar2`");
	
	$Db = new TAllFields();
	$Db->varchar->biggerEq('varchar');
	$Db->varchar->smallerEq('varchar');
	$Db->varchar->bigger('varchar');
	$Db->varchar->smaller('varchar');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` >= 'varchar' AND `varchar` <= 'varchar' AND `varchar` > 'varchar' AND `varchar` < 'varchar'");
	
	$Db = new TAllFields();
	$Db->varchar->md5('varchar');
	$Db->varchar->md5('varchar', true);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'e334e4821b2fa4ff1d5b78c0774a337e' AND MD5(`varchar`) = 'e334e4821b2fa4ff1d5b78c0774a337e'");
	
	$Db = new TAllFields();
	$Db->varchar->compress('varchar');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE COMPRESS(`varchar`) = 'varchar'");
	
	$Db = new TAllFields();
	$Db->varchar->notEmpty();
	$Db->varchar->isEmpty();
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE TRIM(IFNULL(`varchar`,'')) != '' AND TRIM(IFNULL(`varchar`,'')) = ''");
	
	$Db = new TAllFields();
	$Db->varchar->notNull();
	$Db->varchar->null();
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IS NOT NULL AND `varchar` IS NULL");
	
	$Db = new TAllFields();
	$Db->varchar->betweenColumns('int', 'tinyInt');
	$Db->varchar->notBetweenColumns('int', 'tinyInt');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` BETWEEN `int` AND `tinyInt` AND `varchar` BETWEEN `int` AND `tinyInt`");
	
	$Db = new TAllFields();
	$Db->varchar->between(1, 2);
	$Db->varchar->notBetween(1, 2);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` BETWEEN '1' AND '2' AND `varchar` BETWEEN '1' AND '2'");
	
	$Db = new TAllFields();
	$Db->varchar->likeP("'likeP'");
	$Db->varchar->likeP('%likeP%');
	$Db->varchar->notLikeP('likeP');
	$Db->varchar->notLikeP('%likeP%');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` LIKE '%\'likeP\'%' AND `varchar` LIKE '%%likeP%%' AND `varchar` NOT LIKE '%likeP%' AND `varchar` NOT LIKE '%%likeP%%'");
	
	$Db = new TAllFields();
	$Db->varchar->like('%likeP%');
	$Db->varchar->like('%likeP%%');
	$Db->varchar->notLike('likeP%');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` LIKE '%likeP%' AND `varchar` LIKE '%likeP%%' AND `varchar` NOT LIKE 'likeP%'");
	
	$Db = new TAllFields();
	$Db->varchar->now();
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = NOW()");
	
	$Db = new TAllFields();
	$Db->varchar->inSubQuery('SELECT ID FROM table');
	$Db->varchar->notInSubQuery('SELECT ID FROM table');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IN (SELECT ID FROM table) AND `varchar` NOT IN (SELECT ID FROM table)");
	
	$Db = new TAllFields();
	$Db->dateTime->sqlFunction('DATE_FORMAT', ['%Y-%m-%d'])->not('now');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%Y-%m-%d') != NOW()");
	
	$Db = new TAllFields();
	$Db->tinyText('tinyText')->tinyText(123);
	$Db->text('text')->text(123);
	$Db->longText('longText')->longText(123);
	$Db->mediumText('mediumText')->mediumText(123);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `tinyText` = 'tinyText' AND `tinyText` = '123' ) AND ( `text` = 'text' AND `text` = '123' ) AND ( `longText` = 'longText' AND `longText` = '123' ) AND ( `mediumText` = 'mediumText' AND `mediumText` = '123' )");
	
	$Db = new TAllFields();
	$Db->year('now')->year(2075)->year(70)->year(99)->year(0)->year(69);
	$Db->year2('now')->year2(21)->year2(1970)->year2(1999)->year2(2000)->year2(2069)->year2(2069);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `year` = YEAR(NOW()) AND `year` = 2075 AND `year` = 1970 AND `year` = 1999 AND `year` = 2000 AND `year` = 2069 ) AND ( `year2` = YEAR(NOW()) AND `year2` = 21 AND `year2` = 70 AND `year2` = 99 AND `year2` = 0 AND `year2` = 69 AND `year2` = 69 )");
	
	$Db = new TAllFields();
	$Db->timePrec('now')->timePrec('2021-01-21 22:38:39.760')->timePrec('22:38:12')->timePrec('22:38');
	$Db->time('now')->time(null)->time('2021-01-21 22:38:39.760')->time('22:38:12')->time('22:38');
	$Db->date('now')->date('2021-01-21 22:38:39.760')->date('22:38:12')->date('22:38');
	$Db->dateTime('now')->dateTime('2021-01-21 22:38:39.760')->dateTime('22:38:12')->dateTime(3);
	$Db->dateTimePrec('now')->dateTimePrec('2021-01-21 22:38:39.760')->dateTimePrec('22:38:12')->dateTimePrec(3);
	$date = Date::toSqlDate("now");
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `timePrec` = TIME(NOW()) AND `timePrec` = '22:38:39.760' AND `timePrec` = '22:38:12' AND `timePrec` = '22:38:00' ) AND ( `time` = TIME(NOW()) AND `time` = NULL AND `time` = '22:38:39' AND `time` = '22:38:12' AND `time` = '22:38:00' ) AND ( `date` = DATE(NOW()) AND `date` = '2021-01-21' AND `date` = '$date' AND `date` = '$date' ) AND ( `dateTime` = NOW() AND `dateTime` = '2021-01-21 22:38:39' AND `dateTime` = '$date 22:38:12' AND `dateTime` = '1970-01-01 02:00:03' ) AND ( `dateTimePrec` = NOW() AND `dateTimePrec` = '2021-01-21 22:38:39.760' AND `dateTimePrec` = '$date 22:38:12' AND `dateTimePrec` = '1970-01-01 02:00:03' )");
	
	
	$Db = new TAllFields();
	$Db->varchar("'; DELETE FROM customers WHERE 1 or username = '");
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = '\'; DELETE FROM customers WHERE 1 or username = \''");
	
	$Db = new TAllFields();
	$Db->varchar->null();
	$checkQuery($Db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES (NULL)");
	
	$Db = new TAllFields();
	$Db->int->increase(3);
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = `int`+3");
	
	$Db = new TAllFields();
	$Db->int->decrease(3);
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = `int`-3");
	
	$Db = new TAllFields();
	$Db->int->inSubQuery('SELECT ID FROM table');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `int` IN (SELECT ID FROM table)");
	
	$Db = new TAllFields();
	$Db->varchar->inSubQuery("SELECT ID FROM customers WHERE name = 'tere'");
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IN (SELECT ID FROM customers WHERE name = 'tere')");
	
	$Db = new TAllFields();
	$Db->varchar('juhan');
	$Db->collect();
	$Db->varchar('peeter');
	$Db->collect();
	$checkQuery($Db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('juhan'), ('peeter')");
	
	$Db = new TAllFields();
	$Db->varchar('juhan');
	$Db->collect();
	$Db->varchar('peeter');
	$Db->collect();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'juhan';UPDATE `all_fields` SET `varchar` = 'peeter'");
	
	$Db = new TAllFields();
	$Db->varchar('juhan');
	$Db->collect();
	$Db->varchar('peeter');
	$Db->collect();
	$checkQuery($Db->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar`) VALUES ('juhan'), ('peeter')");
	
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->dateTime->now();
	$Db->Where->dateTime("yesterday")->or()->dateTime("first day of this month");
	$yesterDay           = Date::toSqlDate('yesterday');
	$firstDayOfThisMonth = Date::toSqlDateTime('first day of this month');
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `dateTime` = NOW() WHERE ( `dateTime` = '$yesterDay 00:00:00' OR `dateTime` = '$firstDayOfThisMonth' )");
	
	$Db = new TAllFields();
	$Db->ID(1);
	$Db->or()->varchar("test")->varchar->in('testOR');
	$Db->varchar("blaah")->or()->varchar->in('blaahOr');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `ID` = 1 OR ( `varchar` = 'test' AND `varchar` IN ('testOR') ) AND ( `varchar` = 'blaah' OR `varchar` IN ('blaahOr') )");
	
	$Db = new TAllFields();
	$Db->varchar("blaau")->or()->timestamp('now');
	$Db->varchar("ei ei");
	$Db->timestamp->now();
	$yesterDay = Date::toSqlDateTime('yesterday 10:45:31');
	$Db->timestamp($yesterDay);
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` = 'blaau' OR `timestamp` = NOW() ) AND `varchar` = 'ei ei' AND `timestamp` = NOW() AND `timestamp` = '$yesterDay'");
	
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->timestamp->now();
	$checkQuery($Db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`,`timestamp`) VALUES ('blaau',NOW())");
	
	$Db = new TAllFields();
	$Db->ID(1)->or()->varchar('2');
	$Db->or()->varchar("test")->or()->varchar->in('testOR');
	$Db->varchar("blaah")->or()->varchar->in('blaahOr');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `ID` = 1 OR `varchar` = '2' ) OR ( `varchar` = 'test' OR `varchar` IN ('testOR') ) AND ( `varchar` = 'blaah' OR `varchar` IN ('blaahOr') )");
	
	
	$Db = new TAllFields();
	$Db->ID->in([99999, 3])->or()->in([222]);
	$checkQuery($Db->getDeleteQuery(), "DELETE FROM `all_fields` WHERE ( `ID` IN (99999,3) OR `ID` IN (222) )");
	
	
	$Db = new TAllFields();
	$Db->varchar("test2");
	$Db->varchar("test")->or()->varchar->in('testOR');
	$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'test2' AND ( `varchar` = 'test' OR `varchar` IN ('testOR') )");
	
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->timestamp->now();
	$Db->collect();
	
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->timestamp->now();
	$Db->Where->timestamp("yesterday");
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW() WHERE `timestamp` = '" . Date::toSqlDateTime('yesterday') . "'");
	
	
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->timestamp->now();
	$Db->Where->timestamp("yesterday");
	$Db->collect();
	
	$Db->varchar("ehee");
	$Db->timestamp->now();
	$Db->Where->timestamp("tomorrow");
	$Db->collect();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW() WHERE `timestamp` = '" . Date::toSqlDateTime('yesterday') . "';UPDATE `all_fields` SET `varchar` = 'ehee', `timestamp` = NOW() WHERE `timestamp` = '" . Date::toSqlDateTime('tomorrow') . "'");
	
	//Withoud where
	$Db = new TAllFields();
	$Db->varchar("blaau");
	$Db->timestamp->now();
	$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW()");
	
	Db::TAllFields()->varchar("testAutoSave")->delete();
	$Db = new TAllFields();
	$Db->ID(1);
	$Db->varchar("testAutoSave");
	$Db->timestamp("now");
	$Db->dontNullFields();
	$q = $Db->getSaveQuery();
	$checkQuery($q, "INSERT INTO `all_fields` (`ID`,`varchar`,`timestamp`) VALUES (1,'testAutoSave',NOW())");
	Db::query($q);
	$q = $Db->getSaveQuery();
	$checkQuery($q, "UPDATE `all_fields` SET `varchar` = 'testAutoSave', `timestamp` = NOW() WHERE `ID` = 1");
	Db::query($q);
	
	$Db = new TAllFields();
	$Db->timestamp("now");
	$q = $Db->getSaveQuery();
	$checkQuery($q, "INSERT INTO `all_fields` (`timestamp`) VALUES (NOW())");
	Prof()->stopTimer("starter");
	
	exit("tests passed");
	///################# Must throw errors
	$Db = new TAllFields();
	$Db->varchar('varcasdasjdasdjalskdjaslkdjaldjasldjaslkdjalskdaslkdaslkdjaslkjdalskdjalhar');
	debug($Db->getSelectQuery());
	exit;
	
	$Db = new TAllFields();
	//$Db->dateTime('22:38:39.760');
	$Db->dateTime->null();
	//$Db->nullField->null();
	//$Db->dateTime('22:38:39');
	//$Db->dateTime('21.01.2020 22:38:39.760');
	//$Db->dateTime('asdadas');
	//$Db->dateTime('0000:00:00');
	//$Db->dateTime('00:');
	$Db->getSelectQuery();
	
	$Db = new TAllFields();
	$Db->ID->in([99999, 'someRandomString'])->or()->in([222]);
	$Db->getSelectQuery();
	
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
