<?php

use Infira\Poesis\Poesis;
use Infira\Poesis\orm\ComplexValue;
use Infira\Utils\Gen;

$db = new TAllFields();
$db->addRowParser(function ($row)
{
	$row->parserValue1 = 'parser 1';
	
	return $row;
});
$dr = $db->select();
$dr->addRowParser(function ($row)
{
	if (!isset($row->parserValue1))
	{
		\Infira\Poesis\Poesis::error('row parser 1 not runned');
	}
	$row->parserValue2 = 'parser 2';
	
	return $row;
});
$std = $dr->seek(0)->getObject();

if (!is_object($std))
{
	Poesis::error("Should be object", $std);
}
if (get_class($std) != 'stdClass')
{
	Poesis::error("Should be stdClass", $std);
}
if (!isset($std->parserValue1))
{
	\Infira\Poesis\Poesis::error('row parser 1 not runned');
}
if (!isset($std->parserValue2))
{
	\Infira\Poesis\Poesis::error('row parser 2 not runned');
}

class testFetchObjectClass
{
	public function __construct($p = [])
	{
	}
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

$db   = new TAllFields();
$data = (object)['path' => '\my\custom\class'];
$db->Where->ID(1);
$db->longText->json($data);
$db->update();
$json = Db::TAllFields()->ID(1)->select('longText')->getValue('longText');
if ($data != json_decode($json))
{
	Poesis::error("JSON do not match");
}

$db = new TMultiPrimKey();
$db->truncate();
for ($i = 1; $i <= 1000; $i++)
{
	$db->someID($i);
	$arr = ['value1', 'value2', 'value3'];
	$db->someKey($arr[array_rand($arr, 1)]);
	$db->someValue(Gen::randomString(10));
	$db->collect();
}
$db->replace();

if (Db::TMultiPrimKey()->count() != 1000)
{
	Poesis::error("multikey count does not match");
}

$db = new TAllFields();
$db->varchar2("gennu");
$db->collect();
$db->varchar2("siimu");
$db->collect();
$db->insert();
if (!Db::TAllFields()->varchar2('gennu')->count() or !Db::TAllFields()->varchar2('siimu')->count())
{
	Poesis::error("collection insert failed");
}

Db::TAllFields()->varchar2("updateCollection1")->varchar("updateCollection2")->insert();
Db::TAllFields()->tinyText("updateCollection3")->mediumInt(1)->insert();
$db = new TAllFields();
$db->tinyText("modifedRecCollTestValue3");
$db->Where->varchar2('updateCollection1');
$db->Where->varchar('updateCollection2');
$db->collect();

$db->mediumInt(88);
$db->int(99);
$db->Where->tinyText('updateCollection3');
$db->Where->mediumInt(1);
$db->collect();
$db->update();
checkQuery($db->getLastQuery(), "UPDATE `all_fields` SET `tinyText` = 'modifedRecCollTestValue3' WHERE `varchar2` = 'updateCollection1' AND `varchar` = 'updateCollection2';UPDATE `all_fields` SET `mediumInt` = 88, `int` = 99 WHERE `tinyText` = 'updateCollection3' AND `mediumInt` = 1");
if (Db::TAllFields()->varchar2('updateCollection1')->varchar('updateCollection2')->select('tinyText')->getValue('tinyText') !== 'modifedRecCollTestValue3')
{
	Poesis::error('collection update failed');
}
if (Db::TAllFields()->tinyText('updateCollection3')->mediumInt(88)->select('int')->getValue('int') != 99)
{
	Poesis::error('collection update failed');
}

Db::TAllFields()->varchar('deleteCollection1')->varchar2('deleteCollection2')->insert();
Db::TAllFields()->varchar('deleteCollection3')->insert();
$db = new TAllFields();
$db->varchar('deleteCollection1');
$db->varchar2('deleteCollection2');
$db->collect();
$db->varchar('deleteCollection3');
$db->collect();
$db->delete();
checkQuery($db->getLastQuery(), "DELETE FROM `all_fields` WHERE (`varchar` = 'deleteCollection1' AND `varchar2` = 'deleteCollection2') OR (`varchar` = 'deleteCollection3')");
if (Db::TAllFields()->varchar(\Infira\Poesis\orm\ComplexValue::in('deleteCollection1,deleteCollection2'))->hasRows())
{
	Poesis::error('collection delete failed');
}

//region save
Db::TAllFields()->varchar("testAutoSave")->delete();
$Db = new TAllFields();
$Db->ID(1);
$Db->varchar("testAutoSave");
$Db->timestamp("now");
//$Db->haltReset();
$q = $Db->getSaveQuery();
checkQuery($q, "INSERT INTO `all_fields` (`ID`,`varchar`,`timestamp`) VALUES (1,'testAutoSave',NOW())");
Db::query($q);
$q = $Db->getSaveQuery();
checkQuery($q, "UPDATE `all_fields` SET `varchar` = 'testAutoSave', `timestamp` = NOW() WHERE `ID` = 1");
Db::query($q);

$Db = new TAllFields();
$Db->timestamp("now");
$q = $Db->getSaveQuery();
checkQuery($q, "INSERT INTO `all_fields` (`timestamp`) VALUES (NOW())");

Db::TAllFields()->varchar(ComplexValue::in('testAutoSave2,testAutoSave3'))->delete();
Db::TAllFields()->varchar('testAutoSave2')->insert();
$Db = new TAllFields();
$Db->Where->varchar("testAutoSave2");
$Db->varchar("testAutoSave3");
$Db->timestamp("now");
$q = $Db->getSaveQuery();
checkQuery($q, "UPDATE `all_fields` SET `varchar` = 'testAutoSave3', `timestamp` = NOW() WHERE `varchar` = 'testAutoSave2'");
//endregion

//region enum,set

//endregion

//region duplicate
Db::TAllFields()->varchar(\Infira\Poesis\orm\ComplexValue::in('testDuplicateWhere,testDuplicateWhere2'))->delete();
$timestampPrec = '2021-07-28 22:54:58.6381';
Db::TAllFields()->varchar('testDuplicateWhere')->enum('a')->timestampPrec($timestampPrec)->set('')->insert();
Db::TAllFields()->varchar('testDuplicateWhere')->enum('a')->timestampPrec($timestampPrec)->insert();
$db = new TAllFields();
$db->Where->varchar('testDuplicateWhere');
$db->varchar('testDuplicateWhere2');
$q = $db->getDuplicateQuery(['varchar2' => 'testDuplicateWhere3'], ['TID', 'year', 'year2', 'time', 'timePrec', 'date', 'timestamp', 'dateTime', 'dateTimePrec']);
checkQuery($q, "INSERT INTO `all_fields` (`nullField`,`varchar`,`varchar2`,`timestampPrec`,`tinyText`,`text`,`mediumText`,`longText`,`tinyInt`,`smallInt`,`mediumInt`,`int`,`decimal`,`float`,`double`,`real`,`bit`,`tinyBlob`,`blog`,`mediumBlob`,`longBlog`,`enum`,`set`) VALUES (NULL,'testDuplicateWhere2','testDuplicateWhere3','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',''), (NULL,'testDuplicateWhere2','testDuplicateWhere3','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',NULL)");
Db::query($q);
if (Db::TAllFields()->varchar('testDuplicateWhere2')->count() != 2)
{
	Poesis::error('duplicate failed');
}

Db::TAllFields()->varchar(\Infira\Poesis\orm\ComplexValue::in('testDuplicateOverwrite,testDuplicateOverwrite2'))->delete();
$timestampPrec = '2021-07-28 22:54:58.6381';
Db::TAllFields()->varchar('testDuplicateOverwrite')->enum('a')->timestampPrec($timestampPrec)->set('')->insert();
Db::TAllFields()->varchar('testDuplicateOverwrite')->enum('a')->timestampPrec($timestampPrec)->insert();
$db = new TAllFields();
$db->varchar('testDuplicateOverwrite');
$q = $db->getDuplicateQuery(['varchar' => 'testDuplicateOverwrite2'], ['TID', 'year', 'year2', 'time', 'timePrec', 'date', 'timestamp', 'dateTime', 'dateTimePrec']);
checkQuery($q, "INSERT INTO `all_fields` (`nullField`,`varchar`,`varchar2`,`timestampPrec`,`tinyText`,`text`,`mediumText`,`longText`,`tinyInt`,`smallInt`,`mediumInt`,`int`,`decimal`,`float`,`double`,`real`,`bit`,`tinyBlob`,`blog`,`mediumBlob`,`longBlog`,`enum`,`set`) VALUES (NULL,'testDuplicateOverwrite2','','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',''), (NULL,'testDuplicateOverwrite2','','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',NULL)");
Db::query($q);
if (Db::TAllFields()->varchar('testDuplicateOverwrite2')->count() != 2)
{
	Poesis::error('duplicate failed');
}

Db::TAllFields()->varchar(\Infira\Poesis\orm\ComplexValue::in('testDuplicate'))->delete();
$timestampPrec = '2021-07-28 22:54:58.6381';
Db::TAllFields()->varchar('testDuplicate')->enum('a')->timestampPrec($timestampPrec)->set('')->insert();
Db::TAllFields()->varchar('testDuplicate')->enum('a')->timestampPrec($timestampPrec)->insert();
$db = new TAllFields();
$db->varchar('testDuplicate');
$q = $db->getDuplicateQuery(['varchar2' => 'testDuplicate3'], ['TID', 'year', 'year2', 'time', 'timePrec', 'date', 'timestamp', 'dateTime', 'dateTimePrec']);
checkQuery($q, "INSERT INTO `all_fields` (`nullField`,`varchar`,`varchar2`,`timestampPrec`,`tinyText`,`text`,`mediumText`,`longText`,`tinyInt`,`smallInt`,`mediumInt`,`int`,`decimal`,`float`,`double`,`real`,`bit`,`tinyBlob`,`blog`,`mediumBlob`,`longBlog`,`enum`,`set`) VALUES (NULL,'testDuplicate','testDuplicate3','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',''), (NULL,'testDuplicate','testDuplicate3','2021-07-28 22:54:58.6381',NULL,'0',NULL,NULL,0,0,0,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,'a',NULL)");
Db::query($q);
if (Db::TAllFields()->varchar('testDuplicate')->count() != 4)
{
	Poesis::error('duplicate failed');
}

$db = new TAllFields();
$db->varchar('notExits');
$db->varchar('notExits2');
$q = $db->getDuplicateQuery();
if ($q !== null)
{
	Poesis::error('should nothing to delete');
}
//endregion

//region aaa

//endregion

