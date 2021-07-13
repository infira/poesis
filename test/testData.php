<?php

use Infira\Poesis\Poesis;
use Infira\Utils\Gen;

$Db = new TAllFields();
$Db->varchar('varcasdasjdasdjalskdjaslkdjaldjasldjaslkdjalskdaslkdaslkdjaslkjdalskdjalhar');
debug($Db->getUpdateQuery());

$dup = new TAllFields();
$dup->addRowParser(function ($row)
{
	$row->parserValue1 = 'parser 1';
	debug("parser 1");
	
	return $row;
});
$dr = $dup->select();
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

$Db   = new TAllFields();
$data = (object)['path' => '\my\custom\class'];
$Db->Where->ID(1);
$Db->longText->json($data);
$Db->dontNullFields();
$Db->update();

$json = $Db->ID(1)->select('longText')->getValue('longText');
if ($data != json_decode($json))
{
	Poesis::error("JSON do not match");
}

$Db = new TMultiPrimKey();
$Db->truncate();
for ($i = 1; $i <= 1000; $i++)
{
	$Db->someID($i);
	$arr = ['value1', 'value2', 'value3'];
	$Db->someKey($arr[array_rand($arr, 1)]);
	$Db->someValue(Gen::randomString(10));
	$Db->collect();
}
$Db->replace();

if (Db::TMultiPrimKey()->count() != 1000)
{
	Poesis::error("multikey count does not match");
}

$dup = new TAllFields();
$dup->varchar2("gennu");
$dup->collect();
$dup->varchar2("siimu");
$dup->collect();
$dup->insert();

if (!Db::TAllFields()->varchar2('gennu')->count() OR !Db::TAllFields()->varchar2('siimu')->count())
{
	Poesis::error("collect insert failed");
}

//region tID
Poesis::enableTID();
$dup = new TAllFields();
$dup->Where->ID(1);
$dup->varchar2("gen");
$dup->update();
$checkQuery('last record ID = ' . $dup->getLastRecord()->ID, 'last record ID = 1');
$dup = new TAllFields();
$checkQuery($dup->nullFields(true)->varchar("gen")->getUpdateQuery(), '/UPDATE `all_fields` SET `varchar` = \'gen\', `TID` = \'[a-zA-Z0-9]{32}\'/m');
$checkQuery($dup->nullFields(true)->varchar("gen")->getInsertQuery(), '/INSERT INTO `all_fields` \(`varchar`,`TID`\) VALUES \(\'gen\',\'[a-zA-Z0-9]{32}\'\)/m');
$checkQuery($dup->nullFields(true)->varchar("gen")->getReplaceQuery(), '/REPLACE INTO `all_fields` \(`varchar`,`TID`\) VALUES \(\'gen\',\'[a-zA-Z0-9]{32}\'\)/m');
Poesis::disableTID();
//endregion