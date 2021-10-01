<?php

$db = new TAllFields();
$db->varchar("random value");
checkQuery($db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('random value')");
$db->ID->in('1,2,3');
checkQuery($db->getDeleteQuery(), "DELETE FROM `all_fields` WHERE `ID` IN (1,2,3)");



$db = new TAllFields();
$db->varchar("random value");
checkQuery($db->haltReset()->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('random value')");
$db->ID->in('1,2,3');
checkQuery($db->getDeleteQuery(), "DELETE FROM `all_fields` WHERE ( `varchar` = 'random value' AND `ID` IN (1,2,3) )");


$db = new TAllFields();
$db->Where->ID(1);
$ids = $db->select("ID")->getValues("ID");
$db->int(33);
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = 33");

$db = new TAllFields();
$db->Where->ID(1);
$ids = $db->haltReset()->select("ID")->getValues("ID");
$db->int(33);
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = 33 WHERE `ID` = 1");

$db = new TAllFields();
$db->varchar("varchar");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'varchar'");
$db->varchar2("varchar2");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar2` = 'varchar2'");


$db = new TAllFields();
$db->varchar("varchar");
checkQuery($db->haltReset()->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'varchar'");
$db->varchar2("varchar2");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'varchar' AND `varchar2` = 'varchar2'");



$db = new TAllFields();
$db->varchar("varchar");
$db->hasRows();
checkQuery($db->getLastQuery(), "SELECT COUNT(*) as count FROM (SELECT * FROM `all_fields` WHERE `varchar` = 'varchar') AS c");
$db->varchar2("varchar2");
checkQuery($db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar2`) VALUES ('varchar2')");



$db = new TAllFields();
$db->varchar("varchar");
$db->delete();
checkQuery($db->getLastQuery(), "DELETE FROM `all_fields` WHERE `varchar` = 'varchar'");
$db->varchar2("varchar2");
checkQuery($db->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar2`) VALUES ('varchar2')");

$db = new TAllFields();
$db->varchar("varchar");
$db->haltReset()->delete();
checkQuery($db->getLastQuery(), "DELETE FROM `all_fields` WHERE `varchar` = 'varchar'");
$db->varchar2("varchar2");
checkQuery($db->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar`,`varchar2`) VALUES ('varchar','varchar2')");






