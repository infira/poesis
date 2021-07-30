<?php

use Infira\Poesis\Poesis;

Db::TDbLog()->truncate();
Poesis::enableLogger();

$db = new TTid();
$db->varchar("test1");
$db->insert();
$logID = $db->getLastLogID();
if (!$logID and !Db::TDbLog()->ID($logID)->hasRows())
{
	Poesis::error('Logging failed');
}

$db = new TAllFields();
$db->varchar2("collection1");
$db->Where->ID(1);
$db->collect();
$db->varchar2("collection2");
$db->Where->ID(2);
$db->collect();
$db->update();
$logID = $db->getLastLogID();
if (!$logID and !Db::TDbLog()->ID($logID)->hasRows())
{
	Poesis::error('Logging failed');
}

$db = new TMultiPrimKey();
$db->truncate();
$db->PRIMARY_index(1, 'value1');
$db->someValue("modifedRecordInsertTest3");
$db->collect();

$db->PRIMARY_index(2, 'value2');
$db->someValue("modifedRecordInsertTest4");
$db->collect();
$db->insert();
$logID = $db->getLastLogID();
if (!$logID and !Db::TDbLog()->ID($logID)->hasRows())
{
	Poesis::error('Logging failed');
}

$db = new TNoPrim();
$db->truncate();
$db->key('key1');
$db->collect();

$db->key('key2');
$db->collect();
$db->insert();
$logID = $db->getLastLogID();
if (!Db::TDbLog()->ID($logID)->hasRows())
{
	Poesis::error('Logging failed');
}