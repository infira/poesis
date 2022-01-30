<?php

use Infira\Utils\Date;
use Infira\Poesis\Poesis;
use Infira\Poesis\support\Expression;

Poesis::toggleLogger(false);

$db = new TAllFields();
$db->timestamp->columnFunction('DATE')->valueFunction('DATE')->value('now');
checkQuery($db->getSelectQuery(), 'SELECT * FROM `all_fields` WHERE DATE(`timestamp`) = DATE(NOW())');

$db = new TAllFields();
checkQuery($db->getSelectQuery('ID,false as boolField, true as boolField2, md5 ( anotherFunction( varchar  ) ) AS cryped,"" AS null,null AS `nullField`,123 AS numberField, uncompress(blob) AS \'blob\''), 'SELECT `ID`,false AS `boolField`,true AS `boolField2`,md5 (anotherFunction(`varchar`)) AS `cryped`,"" AS `null`,null AS `nullField`,123 AS `numberField`,uncompress(`blob`) AS `blob` FROM `all_fields`');

$tests = [];

//region test method columns
$compCol                      = Expression::column('myColumn');
$compCol2                     = Expression::column('myColumn2');
$tests['select']['varchar'][] = ['biggerEq', $compCol, ">= `myColumn`"];
$tests['select']['varchar'][] = ['bigger', $compCol, "> `myColumn`"];
$tests['select']['varchar'][] = ['smaller', $compCol, "< `myColumn`"];
$tests['select']['varchar'][] = ['smallerEq', $compCol, "<= `myColumn`"];
$tests['select']['varchar'][] = ['not', $compCol, "!= `myColumn`"];
$tests['select']['varchar'][] = ['between', [$compCol, $compCol2], "BETWEEN `myColumn` AND `myColumn2`"];
$tests['select']['varchar'][] = ['notBetween', [$compCol, $compCol2], "NOT BETWEEN `myColumn` AND `myColumn2`"];
//endregion

//region raw
$tests['both']['varchar'][]          = ['value', 'null', "= 'null'"];
$tests['both']['varchar,enum,set'][] = ['value', null, 'NULL'];
$tests['select']['varchar'][]        = ['notValue', 'null', "!= 'null'"];
$tests['both']['varchar,int'][]      = ['raw', '--raw-value--', '--raw-value--'];
$tests['both']['varchar,int'][]      = ['query', 'SELECT `ID` FROM `all_fields` LIMIT 1', '= (SELECT `ID` FROM `all_fields` LIMIT 1)'];
$tests['both']['varchar,int'][]      = ['variable', 'SELECT * FROM table', "= @SELECTFROMtable"];
$tests['both']['varchar,int'][]      = ['variable', '@var', '= @var'];
$tests['both']['varchar,enum,set'][] = ['null', null, 'NULL'];
$tests['edit']['set'][]              = ['value', '', "''"];
$tests['both']['varchar,int'][]      = ['column', "varchar2", "= `varchar2`"];
//endregion

//region select,delete complex value
$tests['select']['varchar'][]     = ['notNull', null, "IS NOT NULL"];
$tests['select']['varchar'][]     = ['notColumn', "varchar2", "!= `varchar2`"];
$tests['select']['varchar'][]     = ['in', 'a', "IN ('a')"];
$tests['select']['varchar'][]     = ['in', 'a,b,3', "IN ('a','b','3')"];
$tests['select']['varchar'][]     = ['in', ['a', 'b', 3], "IN ('a','b','3')"];
$tests['select']['int'][]         = ['in', '1,2,3', "IN (1,2,3)"];
$tests['select']['int'][]         = ['in', [1, 2, 3], "IN (1,2,3)"];
$tests['select']['varchar'][]     = ['notIn', 'a,b,3', "NOT IN ('a','b','3')"];
$tests['select']['varchar'][]     = ['notIn', ['a', 'b', 3], "NOT IN ('a','b','3')"];
$tests['select']['int'][]         = ['notIn', '1,2,3', "NOT IN (1,2,3)"];
$tests['select']['int'][]         = ['notIn', [1, 2, 3], "NOT IN (1,2,3)"];
$tests['select']['varchar,int'][] = ['inSubQuery', 'SELECT * FROM table', "IN (SELECT * FROM table)"];
$tests['select']['varchar,int'][] = ['notInSubQuery', 'SELECT * FROM table', "NOT IN (SELECT * FROM table)"];
$tests['select']['varchar'][]     = ['biggerEq', "biggerEq", ">= 'biggerEq'"];
$tests['select']['int'][]         = ['biggerEq', 3, ">= 3"];
$tests['select']['int'][]         = ['smallerEq', 3, "<= 3"];
$tests['select']['varchar'][]     = ['smallerEq', "smallerEq", "<= 'smallerEq'"];
$tests['select']['varchar'][]     = ['bigger', "bigger", "> 'bigger'"];
$tests['select']['varchar'][]     = ['smaller', "smaller", "< 'smaller'"];
$tests['select']['int'][]         = ['bigger', 3, "> 3"];
$tests['select']['int'][]         = ['smaller', 3, "< 3"];
$tests['select']['varchar'][]     = ['notEmpty', null, "TRIM(IFNULL(`varchar`,'')) != ''"];
$tests['select']['varchar'][]     = ['empty', null, "TRIM(IFNULL(`varchar`,'')) = ''"];
$tests['select']['int'][]         = ['between', [1, 2], "BETWEEN 1 AND 2"];
$tests['select']['varchar'][]     = ['between', [1, 2], "BETWEEN '1' AND '2'"];
$tests['select']['varchar'][]     = ['between', ['value1', 'value2'], "BETWEEN 'value1' AND 'value2'"];
$tests['select']['int'][]         = ['notBetween', [1, 2], "NOT BETWEEN 1 AND 2"];
$tests['select']['int'][]         = ['notBetween', ['1', '2'], "NOT BETWEEN 1 AND 2"];
$tests['select']['varchar'][]     = ['notBetween', ['value1', 'value2'], "NOT BETWEEN 'value1' AND 'value2'"];
$tests['select']['varchar,int'][] = ['betweenColumns', ['col1', 'col2'], "BETWEEN `col1` AND `col2`"];
$tests['select']['varchar,int'][] = ['notBetweenColumns', ['col1', 'col2'], "NOT BETWEEN `col1` AND `col2`"];
$tests['select']['varchar'][]     = ['like', "like", "LIKE 'like'"];
$tests['select']['varchar'][]     = ['notLike', "like", "NOT LIKE 'like'"];
$tests['select']['varchar'][]     = ['like', "%like%", "LIKE '%like%'"];
$tests['select']['varchar'][]     = ['notLike', "%like%", "NOT LIKE '%like%'"];
$tests['select']['varchar'][]     = ['like', "like%", "LIKE 'like%'"];
$tests['select']['varchar'][]     = ['notLike', "like%", "NOT LIKE 'like%'"];
$tests['select']['varchar'][]     = ['like', "%like", "LIKE '%like'"];
$tests['select']['varchar'][]     = ['notLike', "%like", "NOT LIKE '%like'"];
$tests['select']['varchar'][]     = ['likeP', "likeP", "LIKE '%likeP%'"];
$tests['select']['varchar'][]     = ['notLikeP', "likeP", "NOT LIKE '%likeP%'"];
$tests['select']['varchar'][]     = ['likeP', "%likeP%", "LIKE '%%likeP%%'"];
$tests['select']['varchar'][]     = ['notLikeP', "%likeP%", "NOT LIKE '%%likeP%%'"];
$tests['select']['varchar'][]     = ['likeP', "likeP%", "LIKE '%likeP%%'"];
$tests['select']['varchar'][]     = ['notLikeP', "likeP%", "NOT LIKE '%likeP%%'"];
$tests['select']['varchar'][]     = ['likeP', "%likeP", "LIKE '%%likeP%'"];
$tests['select']['varchar'][]     = ['notLikeP', "%likeP", "NOT LIKE '%%likeP%'"];
$tests['select']['int,varchar'][] = ['likeP', "3", "LIKE '%3%'"];
$tests['select']['int,varchar'][] = ['like', "%3%", "LIKE '%3%'"];
$tests['select']['int,varchar'][] = ['like', "%3", "LIKE '%3'"];
$tests['select']['int,varchar'][] = ['like', "3%", "LIKE '3%'"];
$tests['select']['enum'][]        = ['value', "a", "= 'a'"];
$tests['select']['set'][]         = ['value', "a,b", "= 'a,b'"];
//endregion

//region value modifiers
$tests['both']['varchar'][] = ['md5', "md5Value", "= '99b8c7ec58ca70bca4ca8127038a52e9'"];
$tests['both']['varchar'][] = ['compress', "compressValue", "= COMPRESS('compressValue')"];
$tests['edit']['int'][]     = ['increase', 1, '`int` + 1'];
$tests['edit']['int'][]     = ['decrease', 1, '`int` - 1'];
$tests['both']['varchar'][] = ['json', ['name' => 'gen'], "= '{\\\"name\\\":\\\"gen\\\"}'"];
$tests['both']['varchar'][] = ['serialize', ['name' => 'gen'], "= 'a:1:{s:4:\\\"name\\\";s:3:\\\"gen\\\";}'"];
$tests['both']['varchar'][] = ['time', 'yesterday', "= '00:00:00'"];
$tests['both']['varchar'][] = ['date', 'yesterday', "= '" . Date::from('yesterday')->toSqlDate() . "'"];
$tests['both']['varchar'][] = ['dateTime', 'yesterday', "= '" . Date::from('yesterday')->toSqlDateTime() . "'"];
$tests['both']['varchar'][] = ['timestamp', 'yesterday', "= '" . Date::toTime('yesterday') . "'"];
$tests['both']['varchar'][] = ['int', 0, "= '0'"];
$tests['both']['int'][]     = ['int', 0, "= 0"];
$tests['both']['varchar'][] = ['float', 1.1, "= '1.1'"];
$tests['both']['float'][]   = ['float', 1.1, "= 1.1"];
$tests['both']['int'][]     = ['boolInt', true, "= 1"];
$tests['both']['int'][]     = ['boolInt', false, "= 0"];
$tests['both']['int'][]     = ['boolInt', "1", "= 1"];
$tests['both']['int'][]     = ['boolInt', "0", "= 0"];
$tests['both']['float'][]   = ['round', 1.23456789123456789, "= 1.235"];
$tests['both']['varchar'][] = ['substr', 'abcdefghijklnopabcdefghijklnop', "= 'abcdefghijklnopabcdefghij'"];
//endregion

//region run tests
$allTests = ['select' => [], 'edit' => []];
foreach ($tests as $testType => $columns) {
	foreach ($columns as $columnStr => $columnTests) {
		foreach (explode(',', $columnStr) as $column) {
			$column = trim($column);
			if (!isset($allTests['select'][$column])) {
				$allTests['select'][$column] = [];
			}
			if (!isset($allTests['edit'][$column])) {
				$allTests['edit'][$column] = [];
			}
			if ($testType == 'both') {
				$allTests['select'][$column] = array_merge($allTests['select'][$column], $columnTests);
				$allTests['edit'][$column]   = array_merge($allTests['edit'][$column], $columnTests);
			}
			else {
				$allTests[$testType][$column] = array_merge($allTests[$testType][$column], $columnTests);
			}
		}
	}
}
require_once 'dateTests.php';
$selectDateTests = [];
foreach ($dateTests as $column => $fields) {
	foreach ($fields as $k => $test) {
		$method                        = $test[0];
		$value                         = $test[1];
		$test[2]                       = 'DATE:' . $test[2];
		$allTests['select'][$column][] = $test;
		$allTests['edit'][$column][]   = $test;
	}
}
foreach ($allTests as $testType => $columns) {
	foreach ($columns as $column => $columnTests) {
		foreach ($columnTests as $complex) {
			$method = $complex[0];
			$value  = $complex[1];
			$check  = $complex[2];
			
			$fColumn = "`$column`";
			clearExtraErrorInfo();
			addExtraErrorInfo('$column', $column);
			addExtraErrorInfo('$method', $method);
			addExtraErrorInfo('passValue', $value);
			addExtraErrorInfo('$check', '|' . $check . '|');
			
			if (substr($check, 0, 5) == 'DATE:') {
				$check = substr($check, 5);
				if ($testType == 'select') {
					if ($method == 'null' or ($value === null and $method == 'value')) {
						$check = "IS $check";
					}
					else {
						$check = "= $check";
					}
				}
			}
			else {
				if ($method == 'null' or ($value === null and $method == 'value')) {
					if ($testType == 'select') {
						$check = "IS $check";
					}
				}
			}
			
			$db = new TAllFields();
			if (in_array($method, ['betweenColumns', 'notBetweenColumns', 'between', 'notBetween'])) {
				$db->$column->$method($value[0], $value[1]);
			}
			else {
				$db->$column->$method($value);
			}
			addExtraErrorInfo('$method', $method);
			addExtraErrorInfo('$value', $value);
			if ($testType == 'select') {
				if (in_array($method, ['notEmpty', 'empty'])) {
					$fColumn = '';
				}
				else {
					$fColumn = "$fColumn ";
				}
				checkQuery($db->haltReset()->getSelectQuery(), "SELECT * FROM `all_fields` WHERE $fColumn" . $check);
				checkQuery($db->haltReset()->getDeleteQuery(), "DELETE FROM `all_fields` WHERE $fColumn" . $check);
			}
			else {
				$checkUpDate = $check;
				$insertCheck = $check;
				if ($checkUpDate[0] != '=') {
					$checkUpDate = " = $check";
				}
				else {
					$insertCheck = substr($insertCheck, 2);
					$checkUpDate = " $check";
				}
				
				if ($insertCheck[0] == '=') {
					$insertCheck = substr($insertCheck, 2);
				}
				
				addExtraErrorInfo('$checkUpDate', '|' . $checkUpDate . '|');
				checkQuery($db->haltReset()->getUpdateQuery(), "UPDATE `all_fields` SET $fColumn$checkUpDate");
				checkQuery($db->haltReset()->getInsertQuery(), "INSERT INTO `all_fields` ($fColumn) VALUES ($insertCheck)");
				checkQuery($db->haltReset()->getReplaceQuery(), "REPLACE INTO `all_fields` ($fColumn) VALUES ($insertCheck)");
			}
		}
	}
}
//endregion

//region test not
$db = new TAllFields();
$db->varchar->not()->null()->notNull();
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT NULL AND `varchar` NOT NULL )");

$db = new TAllFields();
$db->varchar->not()->in([1, 2])->notIn([1, 2]);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT IN ('1','2') AND `varchar` NOT IN ('1','2') )");

$db = new TAllFields();
$db->varchar->not()->column('varchar2')->notColumn('varchar2');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` != `varchar2` AND `varchar` != `varchar2` )");

$db = new TAllFields();
$db->varchar->not()->inSubQuery('SELECT * FROM table')->notInSubQuery('SELECT * FROM table');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` != (SELECT * FROM table) AND `varchar` != (SELECT * FROM table) )");

$db = new TAllFields();
$db->varchar->not()->empty()->notEmpty();
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( TRIM(IFNULL(`varchar`,'')) != '' AND TRIM(IFNULL(`varchar`,'')) != '' )");

$db = new TAllFields();
$db->varchar->not()->between(1, 2)->notBetween(1, 2);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT BETWEEN '1' AND '2' AND `varchar` NOT BETWEEN '1' AND '2' )");

$db = new TAllFields();
$db->varchar->not()->betweenColumns("col1", "col2")->notBetweenColumns("col1", "col2");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT BETWEEN `col1` AND `col2` AND `varchar` NOT BETWEEN `col1` AND `col2` )");

$db = new TAllFields();
$db->varchar->not()->like('like')->notLike('notLike');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT LIKE 'like' AND `varchar` NOT LIKE 'notLike' )");

$db = new TAllFields();
$db->varchar->rlike('regex');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` RLIKE 'regex'");

$db = new TAllFields();
$db->varchar->password('regex');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = PASSWORD('regex')");

$db = new TAllFields();
$db->varchar->password('regex');
checkQuery($db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES (PASSWORD('regex'))");

$db = new TAllFields();
$db->varchar->not()->colf('md5')->md5('abc');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(`varchar`) != '900150983cd24fb0d6963f7d28e17f72'");

$db = new TAllFields();
$db->varchar->not()->colf('md5')->volf('md5')->value('abc');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(`varchar`) != MD5('abc')");

$db = new TAllFields();
$db->varchar->not()->colf('md5')->colf('DATE_FORMAT', '%d.%m.%Y')->volf('md5')->value('abc');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(DATE_FORMAT(`varchar`,'%d.%m.%Y')) != MD5('abc')");
//

$db = new TAllFields();
$db->raw("`varchar` LIKE 'blaah'");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` LIKE 'blaah'");

$db = new TAllFields();
$db->dateTime->com('<')->now()->or()->com('>')->now();
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `dateTime` < NOW() OR `dateTime` > NOW() )");

$db = new TAllFields();
$db->varchar->in(['varchar', 123, null]);
$db->varchar->notIn(['varchar', 123, null]);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IN ('varchar','123',NULL) AND `varchar` NOT IN ('varchar','123',NULL)");

$db = new TAllFields();
$db->varchar->not('varchar');
$db->varchar->not(123);
$db->varchar->not(null);
$db->varchar(null);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` != 'varchar' AND `varchar` != '123' AND `varchar` IS NOT NULL AND `varchar` IS NULL");

//region column&value functions
$db = new TAllFields();
$db->dateTime->colf('md5')->colf('DATE_FORMAT', '%d.%m.%Y %H:%m:%s')->like('%09.01.2021 15:0%');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s')) LIKE '%09.01.2021 15:0%'");

$db = new TAllFields();
$db->dateTime->colf('md5')->colf('DATE_FORMAT', '%d.%m.%Y %H:%m:%s')->volf('md5')->like('%09.01.2021 15:0%');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s')) LIKE MD5('%09.01.2021 15:0%')");

$db = new TAllFields();
$db->varchar->md5('varchar');
$db->varchar->colf('md5')->md5('varchar');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'e334e4821b2fa4ff1d5b78c0774a337e' AND MD5(`varchar`) = 'e334e4821b2fa4ff1d5b78c0774a337e'");


$db = new TAllFields();
$db->dateTime->colf('DATE_FORMAT', '%d.%m.%Y %H:%m:%s')->like('%09.01.2021 15:0%');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s') LIKE '%09.01.2021 15:0%'");

$db = new TAllFields();
$db->dateTime->colf('DATE_FORMAT', '%Y-%m-%d')->not('now');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%Y-%m-%d') != NOW()");
//endregion

$db = new TAllFields();
$db->tinyText('tinyText')->tinyText(123);
$db->text('text')->text(123);
$db->longText('longText')->longText(123);
$db->mediumText('mediumText')->mediumText(123);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `tinyText` = 'tinyText' AND `tinyText` = '123' ) AND ( `text` = 'text' AND `text` = '123' ) AND ( `longText` = 'longText' AND `longText` = '123' ) AND ( `mediumText` = 'mediumText' AND `mediumText` = '123' )");

$db = new TAllFields();
$db->year('now')->year(2075)->year(70)->year(99)->year(0)->year(69);
$db->year2('now')->year2(21)->year2(1970)->year2(1999)->year2(2000)->year2(2069)->year2(2069);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `year` = YEAR(NOW()) AND `year` = 2075 AND `year` = 1970 AND `year` = 1999 AND `year` = 2000 AND `year` = 2069 ) AND ( `year2` = YEAR(NOW()) AND `year2` = 21 AND `year2` = 70 AND `year2` = 99 AND `year2` = 0 AND `year2` = 69 AND `year2` = 69 )");

$db = new TAllFields();
$db->timePrec('now')->timePrec('2021-01-21 22:38:39.760')->timePrec('22:38:12')->timePrec('22:38');
$db->time('now')->time(null)->time('2021-01-21 22:38:39.760')->time('22:38:12')->time('22:38');
$db->date('now')->date('2021-01-21 22:38:39.760')->date('22:38:12')->date('22:38');
$db->dateTime('now')->dateTime('2021-01-21 22:38:39.760')->dateTime('22:38:12')->dateTime(3);
$db->dateTimePrec('now')->dateTimePrec('2021-01-21 22:38:39.760')->dateTimePrec('22:38:12')->dateTimePrec(3);
$date = Date::from("now")->toSqlDate();
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `timePrec` = TIME(NOW()) AND `timePrec` = '22:38:39.76000' AND `timePrec` = '22:38:12.00000' AND `timePrec` = '22:38:00.00000' ) AND ( `time` = TIME(NOW()) AND `time` IS NULL AND `time` = '22:38:39' AND `time` = '22:38:12' AND `time` = '22:38:00' ) AND ( `date` = NOW() AND `date` = '2021-01-21' AND `date` = '$date' AND `date` = '$date' ) AND ( `dateTime` = NOW() AND `dateTime` = '2021-01-21 22:38:39' AND `dateTime` = '$date 22:38:12' AND `dateTime` = '1970-01-01 02:00:03' ) AND ( `dateTimePrec` = NOW() AND `dateTimePrec` = '2021-01-21 22:38:39.760' AND `dateTimePrec` = '$date 22:38:12.000' AND `dateTimePrec` = '1970-01-01 02:00:03.000' )");

$db = new TAllFields();
$db->varchar("'; DELETE FROM customers WHERE 1 or username = '");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = '\'; DELETE FROM customers WHERE 1 or username = \''");

$db = new TAllFields();
$db->ID(1);
$db->or()->varchar("test")->varchar->in('testOR');
$db->varchar("blaah")->or()->varchar->in('blaahOr');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `ID` = 1 OR ( `varchar` = 'test' AND `varchar` IN ('testOR') ) AND ( `varchar` = 'blaah' OR `varchar` IN ('blaahOr') )");

$db = new TAllFields();
$db->varchar("blaau")->or()->timestamp('now');
$db->varchar("ei ei");
$db->timestamp->now();
$yesterDay = Date::from('yesterday 10:45:31')->toSqlDateTime();
$db->timestamp($yesterDay);
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` = 'blaau' OR `timestamp` = NOW() ) AND `varchar` = 'ei ei' AND `timestamp` = NOW() AND `timestamp` = '$yesterDay'");

$db = new TAllFields();
$db->ID(1)->or()->varchar('2');
$db->or()->varchar("test")->or()->varchar->in('testOR');
$db->varchar("blaah")->or()->varchar->in('blaahOr');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `ID` = 1 OR `varchar` = '2' ) OR ( `varchar` = 'test' OR `varchar` IN ('testOR') ) AND ( `varchar` = 'blaah' OR `varchar` IN ('blaahOr') )");

$db = new TAllFields();
$db->varchar("test2");
$db->varchar("test")->or()->varchar->in('testOR');
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'test2' AND ( `varchar` = 'test' OR `varchar` IN ('testOR') )");
//endregion

//region collection queryies
$db = new TAllFields();
$db->varchar('collectionValue1');
$db->Where->varchar("test");
$db->collect();
$db->varchar('collectionValue3');
$db->Where->varchar("test3");
$db->collect();
checkQuery($db->haltReset()->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('collectionValue1'), ('collectionValue3')");
checkQuery($db->haltReset()->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar`) VALUES ('collectionValue1'), ('collectionValue3')");
checkQuery($db->haltReset()->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'collectionValue1' WHERE `varchar` = 'test';UPDATE `all_fields` SET `varchar` = 'collectionValue3' WHERE `varchar` = 'test3'");
checkQuery($db->haltReset()->getDeleteQuery(), "DELETE FROM `all_fields` WHERE `varchar` = 'test' OR `varchar` = 'test3'");


$db = new TAllFields();
$db->varchar('collectionValue1');
$db->collect();
$db->varchar('collectionValue3');
$db->collect();
checkQuery($db->haltReset()->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('collectionValue1'), ('collectionValue3')");
checkQuery($db->haltReset()->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar`) VALUES ('collectionValue1'), ('collectionValue3')");
checkQuery($db->haltReset()->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'collectionValue1';UPDATE `all_fields` SET `varchar` = 'collectionValue3'");
checkQuery($db->haltReset()->getDeleteQuery(), "DELETE FROM `all_fields` WHERE `varchar` = 'collectionValue1' OR `varchar` = 'collectionValue3'");

$db = new TAllFields();
$db->varchar('collectionValue1');
$db->varchar2('collectionValue2');
$db->collect();
$db->varchar('collectionValue3');
$db->collect();
checkQuery($db->haltReset()->getDeleteQuery(), "DELETE FROM `all_fields` WHERE (`varchar` = 'collectionValue1' AND `varchar2` = 'collectionValue2') OR `varchar` = 'collectionValue3'");

$db = new TAllFields();
$db->varchar("blaau");
$db->timestamp->now();
$db->Where->timestamp("yesterday");
$db->collect();

$db->varchar("ehee");
$db->timestamp->now();
$db->Where->timestamp("tomorrow");
$db->collect();
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW() WHERE `timestamp` = '" . Date::from('yesterday')->toSqlDateTime() . "';UPDATE `all_fields` SET `varchar` = 'ehee', `timestamp` = NOW() WHERE `timestamp` = '" . Date::from('tomorrow')->toSqlDateTime() . "'");
//endregion

//region update with where
$db = new TAllFields();
$db->varchar("blaau");
$db->dateTime->now();
$db->Where->dateTime("yesterday")->or()->dateTime("first day of this month");
$yesterDay           = Date::from('yesterday')->toSqlDate();
$firstDayOfThisMonth = Date::from('first day of this month')->toSqlDateTime();
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `dateTime` = NOW() WHERE ( `dateTime` = '$yesterDay 00:00:00' OR `dateTime` = '$firstDayOfThisMonth' )");

$db = new TAllFields();
$db->varchar("blaau");
$db->timestamp->now();
$db->Where->timestamp("yesterday");
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW() WHERE `timestamp` = '" . Date::from('yesterday')->toSqlDateTime() . "'");

$db = Db::TAllFields()->int(999)->float(222)->Where->varchar2('name')->dateTime('now');
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = 999, `float` = 222 WHERE ( `varchar2` = 'name' AND `dateTime` = NOW() )");
//endregion

//region tID
$db = new TTid();
checkQuery($db->varchar("gen")->getUpdateQuery(), '/UPDATE `tid` SET `varchar` = \'gen\', `TID` = \'[a-zA-Z0-9]{32}\'/m');
checkQuery($db->varchar("gen")->getInsertQuery(), '/INSERT INTO `tid` \(`varchar`,`TID`\) VALUES \(\'gen\',\'[a-zA-Z0-9]{32}\'\)/m');
checkQuery($db->varchar("gen")->getReplaceQuery(), '/REPLACE INTO `tid` \(`varchar`,`TID`\) VALUES \(\'gen\',\'[a-zA-Z0-9]{32}\'\)/m');
//endregion

$db = new TAllFields();
checkQuery($db->getSelectQuery("DATE_FORMAT(insertDate,'%m-%Y') as dkey"), "SELECT DATE_FORMAT(insertDate,'%m-%Y') AS `dkey` FROM `all_fields`");

$db = new TAllFields();
$db->addWhereClauseColumnValueParser('ID', function ($value)
{
	if ($value > 1) {
		return 999;
	}
	else {
		return 888;
	}
});
$db->addClauseColumnValueParser('int', function ($value)
{
	if ($value > 1) {
		return 999;
	}
	else {
		return 888;
	}
});
$db->Where->ID(1);
$db->int(33);
checkQuery($db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = 999 WHERE `ID` = 888");

