<?php

use Infira\Poesis\Poesis;

//region update getModifedRecord
Db::TAllFields()->varchar2("modifedRecordTest")->delete();
Db::TAllFields()->varchar2("modifedRecordTest")->insert();
$db = new TAllFields();
$db->varchar("modifedRecordTestValue");
$db->Where->varchar2('modifedRecordTest');
$db->update();
$last = $db->getAffectedRecord();
if (!$last)
{
	Poesis::error("getAffectedRecord should return object");
}
elseif ($last->varchar != 'modifedRecordTestValue')
{
	Poesis::error("getAffectedRecord value incorrect");
}
Db::TAllFields()->varchar2("modifedRecordTest")->varchar("modifedRecordTestValue2")->insert();
$lasts = $db->getAffectedRecordModel()->select()->getObjects();
if (!is_array($lasts))
{
	Poesis::error("getAffectedRecordModel should return array");
}

//with collection
Db::TAllFields()->varchar2("modifedRecCollTest1")->varchar("modifedRecCollTestValue1")->insert();
Db::TAllFields()->tinyText("modifedRecCollTest2")->mediumInt(1)->insert();
$db = new TAllFields();
$db->varchar("modifedRecCollTestValue3");
$db->Where->varchar2('modifedRecCollTest1');
$db->Where->varchar('modifedRecCollTestValue1');
$db->collect();

$db->mediumInt(88);
$db->int(1);
$db->Where->tinyText('modifedRecCollTest2');
$db->Where->mediumInt(1);
$db->collect();
$db->update();
$lasts = $db->getAffectedRecordModel()->select('varchar,mediumInt,int')->getObjects();
if (!is_array($lasts))
{
	Poesis::error("getAffectedRecordModel should return array");
}
if ($lasts[0]->varchar != 'modifedRecCollTestValue3')
{
	Poesis::error("getAffectedRecord value incorrect");
}
if ($lasts[1]->mediumInt != 88 or $lasts[1]->int != 1)
{
	Poesis::error("getAffectedRecord value incorrect");
}
//endregion

//region insert getModifedRecord
$db = new TAllFields();
$db->varchar2("modifedRecordInsertTest");
$db->insert();
checkQuery($db->getLastQuery(), "INSERT INTO `all_fields` (`varchar2`) VALUES ('modifedRecordInsertTest')");
checkQuery($db->getAffectedRecordModel()->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `ID` = 26");


$db = new TMultiPrimKey();
$db->truncate();
$db->PRIMARY_index(1, 'value1');
$db->someValue("modifedRecordInsertTest3");
$db->collect();

$db->PRIMARY_index(2, 'value2');
$db->someValue("modifedRecordInsertTest4");
$db->collect();
$db->insert();
checkQuery($db->getLastQuery(), "INSERT INTO `multi_prim_key` (`someID`,`someKey`,`someValue`) VALUES (1,'value1','modifedRecordInsertTest3'), (2,'value2','modifedRecordInsertTest4')");
checkQuery($db->getAffectedRecordModel()->getSelectQuery(), "SELECT * FROM `multi_prim_key` WHERE ( `someID` = 1 AND `someKey` = 'value1' ) OR ( `someID` = 2 AND `someKey` = 'value2' )");
//endregion

$db = new TTid();
$db->varchar("tidTest");
$db->update();
checkQuery($db->getAffectedRecordModel()->getSelectQuery(), '/SELECT \* FROM `tid` WHERE `TID` = \'[a-zA-Z0-9]{32}\'/m');

$db = new TTid();
$db->Where->ID(1);
$db->varchar("gen");
$db->update();
checkQuery('last record ID = ' . $db->getAffectedRecord()->ID, 'last record ID = 1');
