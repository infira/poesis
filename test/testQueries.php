<?php

use Infira\Utils\Date;
use Infira\Poesis\orm\ComplexValue;
use Infira\Poesis\Poesis;

$checkQuery = function ($query, $correct)
{
	$query = str_replace(["\n"], '', $query);
	if ($correct{0} == '/')
	{
		if (!\Infira\Utils\Regex::isMatch($correct, $query))
		{
			$ei                  = [];
			$ei[' actual query'] = $query;
			$ei['correct query'] = $correct;
			$ei['trace']         = getTrace();
			Poesis::error("Compile error", $ei);
		}
	}
	else
	{
		$correct = str_replace(["\n"], '', $correct);
		if ($query != $correct)
		{
			$ei                  = [];
			$ei[' actual query'] = $query;
			$ei['correct query'] = $correct;
			$ei['trace']         = getTrace();
			Poesis::error("Compile error", $ei);
		}
	}
};

$Db = new TAllFields();
$checkQuery($Db->getSelectQuery('ID,false as boolField, true as boolField2, md5 ( anotherFunction( varchar  ) ) AS cryped,"" AS null,null AS `nullField`,123 AS numberField, uncompress(blob) AS \'blob\''), 'SELECT `ID`,false AS `boolField`,true AS `boolField2`,md5 (anotherFunction(`varchar`)) AS `cryped`,"" AS `null`,null AS `nullField`,123 AS `numberField`,uncompress(`blob`) AS `blob` FROM `all_fields`');

$tests = [];

//region test method columns
$compCol                      = ComplexValue::column('myColumn');
$compCol2                     = ComplexValue::column('myColumn2');
$tests['select']['varchar'][] = ['biggerEq', $compCol, ">= `myColumn`"];
$tests['select']['varchar'][] = ['bigger', $compCol, "> `myColumn`"];
$tests['select']['varchar'][] = ['smaller', $compCol, "< `myColumn`"];
$tests['select']['varchar'][] = ['smallerEq', $compCol, "<= `myColumn`"];
$tests['select']['varchar'][] = ['not', $compCol, "!= `myColumn`"];
$tests['select']['varchar'][] = ['between', [$compCol, $compCol2], "BETWEEN `myColumn` AND `myColumn2`"];
$tests['select']['varchar'][] = ['notBetween', [$compCol, $compCol2], "NOT BETWEEN `myColumn` AND `myColumn2`"];
//endregion

//region raw
$tests['both']['varchar'][]     = ['value', 'null', "= 'null'"];
$tests['both']['varchar'][]     = ['value', null, 'NULL'];
$tests['select']['varchar'][]   = ['notValue', 'null', "!= 'null'"];
$tests['both']['varchar,int'][] = ['raw', '--raw-value--', '--raw-value--'];
$tests['both']['varchar,int'][] = ['query', 'SELECT `ID` FROM `all_fields` LIMIT 1', '= (SELECT `ID` FROM `all_fields` LIMIT 1)'];
$tests['both']['varchar,int'][] = ['variable', 'SELECT * FROM table', "= @SELECTFROMtable"];
$tests['both']['varchar,int'][] = ['variable', '@var', '= @var'];
$tests['both']['varchar'][]     = ['null', null, 'NULL'];
$tests['both']['varchar,int'][] = ['column', "varchar2", "= `varchar2`"];
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
//endregion

//region value modifiers
$tests['both']['varchar'][] = ['md5', "md5Value", "= '99b8c7ec58ca70bca4ca8127038a52e9'"];
$tests['both']['varchar'][] = ['compress', "compressValue", "= COMPRESS('compressValue')"];
$tests['edit']['int'][]     = ['increase', 1, '`int` + 1'];
$tests['edit']['int'][]     = ['decrease', 1, '`int` - 1'];
$tests['both']['varchar'][] = ['json', ['name' => 'gen'], "= '{\\\"name\\\":\\\"gen\\\"}'"];
$tests['both']['varchar'][] = ['serialize', ['name' => 'gen'], "= 'a:1:{s:4:\\\"name\\\";s:3:\\\"gen\\\";}'"];
$tests['both']['varchar'][] = ['time', 'yesterday', "= '00:00:00'"];
$tests['both']['varchar'][] = ['date', 'yesterday', "= '" . Date::toSqlDate('yesterday') . "'"];
$tests['both']['varchar'][] = ['dateTime', 'yesterday', "= '" . Date::toSqlDateTime('yesterday') . "'"];
$tests['both']['varchar'][] = ['timestamp', 'yesterday', "= '" . Date::toTime('yesterday') . "'"];
$tests['both']['varchar'][] = ['int', 0, "= '0'"];
$tests['both']['int'][]     = ['int', 0, "= 0"];
$tests['both']['varchar'][] = ['float', 1.1, "= '1.1'"];
$tests['both']['float'][]   = ['float', 1.1, "= 1.1"];
$tests['both']['int'][]     = ['boolInt', true, "= 1"];
$tests['both']['int'][]     = ['boolInt', false, "= 0"];
$tests['both']['float'][]   = ['round', 1.23456789123456789, "= 1.235"];
$tests['both']['varchar'][] = ['substr', 'abcdefghijklnopabcdefghijklnop', "= 'abcdefghijklnopabcdefghij'"];
//endregion

//region run tests
$allTests = ['select' => [], 'edit' => []];
foreach ($tests as $testType => $columns)
{
	foreach ($columns as $columnStr => $columnTests)
	{
		foreach (explode(',', $columnStr) as $column)
		{
			$column = trim($column);
			if (!isset($allTests['select'][$column]))
			{
				$allTests['select'][$column] = [];
			}
			if (!isset($allTests['edit'][$column]))
			{
				$allTests['edit'][$column] = [];
			}
			if ($testType == 'both')
			{
				$allTests['select'][$column] = array_merge($allTests['select'][$column], $columnTests);
				$allTests['edit'][$column]   = array_merge($allTests['edit'][$column], $columnTests);
			}
			else
			{
				$allTests[$testType][$column] = array_merge($allTests[$testType][$column], $columnTests);
			}
		}
	}
}
require_once 'dateTests.php';
$selectDateTests = [];
foreach ($dateTests as $column => $fields)
{
	foreach ($fields as $k => $test)
	{
		$method                        = $test[0];
		$value                         = $test[1];
		$test[2]                       = 'DATE:' . $test[2];
		$allTests['select'][$column][] = $test;
		$allTests['edit'][$column][]   = $test;
	}
}
foreach ($allTests as $testType => $columns)
{
	foreach ($columns as $column => $columnTests)
	{
		foreach ($columnTests as $complex)
		{
			$method = $complex[0];
			$value  = $complex[1];
			$check  = $complex[2];
			
			$fColumn = "`$column`";
			clearExtraErrorInfo();
			addExtraErrorInfo('$column', $column);
			addExtraErrorInfo('$method', $method);
			addExtraErrorInfo('passValue', $value);
			addExtraErrorInfo('$check', '|' . $check . '|');
			
			if (substr($check, 0, 5) == 'DATE:')
			{
				$check = substr($check, 5);
				if ($testType == 'select')
				{
					if ($method == 'null' or ($value === null and $method == 'value'))
					{
						$check = "IS $check";
					}
					else
					{
						$check = "= $check";
					}
				}
			}
			else
			{
				if ($method == 'null' or ($value === null and $method == 'value'))
				{
					if ($testType == 'select')
					{
						$check = "IS $check";
					}
				}
			}
			
			$Db = new TAllFields();
			if (in_array($method, ['betweenColumns', 'notBetweenColumns', 'between', 'notBetween']))
			{
				$Db->$column->$method($value[0], $value[1]);
			}
			else
			{
				$Db->$column->$method($value);
			}
			if ($testType == 'select')
			{
				if (in_array($method, ['notEmpty', 'empty']))
				{
					$fColumn = '';
				}
				else
				{
					$fColumn = "$fColumn ";
				}
				$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE $fColumn" . $check);
				$checkQuery($Db->getDeleteQuery(), "DELETE FROM `all_fields` WHERE $fColumn" . $check);
			}
			else
			{
				$checkUpDate = $check;
				$insertCheck = $check;
				if ($checkUpDate{0} != '=')
				{
					$checkUpDate = " = $check";
				}
				else
				{
					$insertCheck = substr($insertCheck, 2);
					$checkUpDate = " $check";
				}
				$checkUpDate = str_replace('= --raw-value--', '--raw-value--', $checkUpDate);
				
				if ($insertCheck{0} == '=')
				{
					$insertCheck = substr($insertCheck, 2);
				}
				
				addExtraErrorInfo('$checkUpDate', '|' . $checkUpDate . '|');
				$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET $fColumn$checkUpDate");
				$checkQuery($Db->getInsertQuery(), "INSERT INTO `all_fields` ($fColumn) VALUES ($insertCheck)");
				$checkQuery($Db->getReplaceQuery(), "REPLACE INTO `all_fields` ($fColumn) VALUES ($insertCheck)");
			}
		}
	}
}
//endregion

//region test not
$Db = new TAllFields();
$Db->varchar->not()->null()->notNull();
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT NULL AND `varchar` NOT NULL )");

$Db = new TAllFields();
$Db->varchar->not()->in([1, 2])->notIn([1, 2]);
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT IN ('1','2') AND `varchar` NOT IN ('1','2') )");

$Db = new TAllFields();
$Db->varchar->not()->column('varchar2')->notColumn('varchar2');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` != `varchar2` AND `varchar` != `varchar2` )");

$Db = new TAllFields();
$Db->varchar->not()->inSubQuery('SELECT * FROM table')->notInSubQuery('SELECT * FROM table');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` != (SELECT * FROM table) AND `varchar` != (SELECT * FROM table) )");

$Db = new TAllFields();
$Db->varchar->not()->empty()->notEmpty();
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( TRIM(IFNULL(`varchar`,'')) != '' AND TRIM(IFNULL(`varchar`,'')) != '' )");

$Db = new TAllFields();
$Db->varchar->not()->between(1, 2)->notBetween(1, 2);
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT BETWEEN '1' AND '2' AND `varchar` NOT BETWEEN '1' AND '2' )");

$Db = new TAllFields();
$Db->varchar->not()->betweenColumns("col1", "col2")->notBetweenColumns("col1", "col2");
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT BETWEEN `col1` AND `col2` AND `varchar` NOT BETWEEN `col1` AND `col2` )");

$Db = new TAllFields();
$Db->varchar->not()->like('like')->notLike('notLike');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `varchar` NOT LIKE 'like' AND `varchar` NOT LIKE 'notLike' )");

$Db = new TAllFields();
$Db->varchar->not()->colf('md5')->md5('abc');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(`varchar`) != '900150983cd24fb0d6963f7d28e17f72'");

$Db = new TAllFields();
$Db->varchar->not()->colf('md5')->volf('md5')->value('abc');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(`varchar`) != MD5('abc')");
//

$Db = new TAllFields();
$Db->raw(" `varchar` LIKE 'blaah'");
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` LIKE 'blaah'");

$Db = new TAllFields();
$Db->dateTime->lop('<')->now()->or()->lop('>')->now();
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `dateTime` < NOW() OR `dateTime` > NOW() )");

$Db = new TAllFields();
$Db->varchar->in(['varchar', 123, null]);
$Db->varchar->notIn(['varchar', 123, null]);
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` IN ('varchar','123',NULL) AND `varchar` NOT IN ('varchar','123',NULL)");

$Db = new TAllFields();
$Db->varchar->not('varchar');
$Db->varchar->not(123);
$Db->varchar->not(null);
$Db->varchar(null);
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` != 'varchar' AND `varchar` != '123' AND `varchar` IS NOT NULL AND `varchar` IS NULL");

//region column&value functions
$Db = new TAllFields();
$Db->dateTime->colf('DATE_FORMAT', ['%d.%m.%Y %H:%m:%s'])->colf('md5')->like('%09.01.2021 15:0%');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s')) LIKE '%09.01.2021 15:0%'");

$Db = new TAllFields();
$Db->dateTime->colf('DATE_FORMAT', ['%d.%m.%Y %H:%m:%s'])->colf('md5')->volf('md5')->like('%09.01.2021 15:0%');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE MD5(DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s')) LIKE '%09.01.2021 15:0%'");

$Db = new TAllFields();
$Db->varchar->md5('varchar');
$Db->varchar->colf('md5')->md5('varchar');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'e334e4821b2fa4ff1d5b78c0774a337e' AND MD5(`varchar`) = 'e334e4821b2fa4ff1d5b78c0774a337e'");


$Db = new TAllFields();
$Db->dateTime->colf('DATE_FORMAT', ['%d.%m.%Y %H:%m:%s'])->like('%09.01.2021 15:0%');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%d.%m.%Y %H:%m:%s') LIKE '%09.01.2021 15:0%'");

$Db = new TAllFields();
$Db->dateTime->colf('DATE_FORMAT', ['%Y-%m-%d'])->not('now');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE DATE_FORMAT(`dateTime`,'%Y-%m-%d') != NOW()");
//endregion

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
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `timePrec` = TIME(NOW()) AND `timePrec` = '22:38:39.76000' AND `timePrec` = '22:38:12.00000' AND `timePrec` = '22:38:00.00000' ) AND ( `time` = TIME(NOW()) AND `time` IS NULL AND `time` = '22:38:39' AND `time` = '22:38:12' AND `time` = '22:38:00' ) AND ( `date` = NOW() AND `date` = '2021-01-21' AND `date` = '$date' AND `date` = '$date' ) AND ( `dateTime` = NOW() AND `dateTime` = '2021-01-21 22:38:39' AND `dateTime` = '$date 22:38:12' AND `dateTime` = '1970-01-01 02:00:03' ) AND ( `dateTimePrec` = NOW() AND `dateTimePrec` = '2021-01-21 22:38:39.760' AND `dateTimePrec` = '$date 22:38:12.000' AND `dateTimePrec` = '1970-01-01 02:00:03.000' )");

$Db = new TAllFields();
$Db->varchar("'; DELETE FROM customers WHERE 1 or username = '");
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = '\'; DELETE FROM customers WHERE 1 or username = \''");

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
$Db->ID(1)->or()->varchar('2');
$Db->or()->varchar("test")->or()->varchar->in('testOR');
$Db->varchar("blaah")->or()->varchar->in('blaahOr');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE ( `ID` = 1 OR `varchar` = '2' ) OR ( `varchar` = 'test' OR `varchar` IN ('testOR') ) AND ( `varchar` = 'blaah' OR `varchar` IN ('blaahOr') )");

$Db = new TAllFields();
$Db->varchar("test2");
$Db->varchar("test")->or()->varchar->in('testOR');
$checkQuery($Db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'test2' AND ( `varchar` = 'test' OR `varchar` IN ('testOR') )");
//endregion

//region collection queryies
$Db = new TAllFields();
$Db->varchar('juhan');
$Db->collect();
$Db->varchar('peeter');
$Db->collect();
$checkQuery($Db->getInsertQuery(), "INSERT INTO `all_fields` (`varchar`) VALUES ('juhan'), ('peeter')");
$checkQuery($Db->getReplaceQuery(), "REPLACE INTO `all_fields` (`varchar`) VALUES ('juhan'), ('peeter')");
$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'juhan';UPDATE `all_fields` SET `varchar` = 'peeter'");

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
//endregion


//region update with where
$Db = new TAllFields();
$Db->varchar("blaau");
$Db->dateTime->now();
$Db->Where->dateTime("yesterday")->or()->dateTime("first day of this month");
$yesterDay           = Date::toSqlDate('yesterday');
$firstDayOfThisMonth = Date::toSqlDateTime('first day of this month');
$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `dateTime` = NOW() WHERE ( `dateTime` = '$yesterDay 00:00:00' OR `dateTime` = '$firstDayOfThisMonth' )");

$Db = new TAllFields();
$Db->varchar("blaau");
$Db->timestamp->now();
$Db->Where->timestamp("yesterday");
$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `varchar` = 'blaau', `timestamp` = NOW() WHERE `timestamp` = '" . Date::toSqlDateTime('yesterday') . "'");

$Db = Db::TAllFields()->int(999)->float(222)->Where->varchar2('name')->dateTime('now');
$checkQuery($Db->getUpdateQuery(), "UPDATE `all_fields` SET `int` = 999, `float` = 222 WHERE ( `varchar2` = 'name' AND `dateTime` = NOW() )");
//endregion

//region without where
$dup   = new TAllFields();
$tests = [];

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

