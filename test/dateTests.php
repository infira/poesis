<?php

$dateTests['year'][] = ['value', 'now', 'YEAR(NOW())'];
$dateTests['year'][] = ['now', null, 'YEAR(NOW())'];
$dateTests['year'][] = ['value', '2021-01-21 22:38:39.1234567', "2021"];
$dateTests['year'][] = ['value', '2021-01-21 22:38:39.123', "2021"];
$dateTests['year'][] = ['value', '2021-01-21 22:38', "2021"];
$dateTests['year'][] = ['value', '22:38:39.1234567', date('Y')];
$dateTests['year'][] = ['value', '22:38:39.123', date('Y')];
$dateTests['year'][] = ['value', '22:38', date('Y')];
$dateTests['year'][] = ['value', 69, 2069];
$dateTests['year'][] = ['value', 30, 2030];
$dateTests['year'][] = ['value', 0, 2000];
$dateTests['year'][] = ['value', 70, 1970];
$dateTests['year'][] = ['value', 80, 1980];
$dateTests['year'][] = ['value', 99, 1999];
$dateTests['year'][] = ['value', 1901, 1901];
$dateTests['year'][] = ['value', 1999, 1999];
$dateTests['year'][] = ['value', 2155, 2155];
$dateTests['year'][] = ['value', 2100, 2100];
$dateTests['year'][] = ['null', null, 'NULL'];
$dateTests['year'][] = ['value', null, 'NULL'];


$dateTests['year2'][] = ['value', 'now', 'YEAR(NOW())'];
$dateTests['year2'][] = ['now', null, 'YEAR(NOW())'];
$dateTests['year2'][] = ['value', '2021-01-21 22:38:39.1234567', "2021"];
$dateTests['year2'][] = ['value', '2021-01-21 22:38:39.123', "2021"];
$dateTests['year2'][] = ['value', '2021-01-21 22:38', "2021"];
$dateTests['year2'][] = ['value', '22:38:39.1234567', date('Y')];
$dateTests['year2'][] = ['value', '22:38:39.123', date('Y')];
$dateTests['year2'][] = ['value', '22:38', date('Y')];
$dateTests['year2'][] = ['value', 0, 0];
$dateTests['year2'][] = ['value', 30, 30];
$dateTests['year2'][] = ['value', 99, 99];
$dateTests['year2'][] = ['value', 1970, 70];
$dateTests['year2'][] = ['value', 1976, 76];
$dateTests['year2'][] = ['value', 1999, 99];
$dateTests['year2'][] = ['value', 2000, 0];
$dateTests['year2'][] = ['value', 2030, 30];
$dateTests['year2'][] = ['value', 2069, 69];
$dateTests['year2'][] = ['null', null, 'NULL'];
$dateTests['year2'][] = ['value', null, 'NULL'];

$dateTests['time'][] = ['value', 'now', 'TIME(NOW())'];
$dateTests['time'][] = ['now', null, 'TIME(NOW())'];
$dateTests['time'][] = ['value', '2021-01-21 22:38:39.1234567', "'22:38:39'"];
$dateTests['time'][] = ['value', '2021-01-21 22:38:39', "'22:38:39'"];
$dateTests['time'][] = ['value', '2021-01-21 22:38', "'22:38:00'"];
$dateTests['time'][] = ['value', '22:38:39.1234567', "'22:38:39'"];
$dateTests['time'][] = ['value', '22:38:39', "'22:38:39'"];
$dateTests['time'][] = ['value', '22:38', "'22:38:00'"];
$dateTests['time'][] = ['null', null, 'NULL'];
$dateTests['time'][] = ['value', null, 'NULL'];

$dateTests['timePrec'][] = ['value', 'now', 'TIME(NOW())'];
$dateTests['timePrec'][] = ['now', null, 'TIME(NOW())'];
$dateTests['timePrec'][] = ['value', '2021-01-21 22:38:39.1234567', "'22:38:39.12345'"];
$dateTests['timePrec'][] = ['value', '2021-01-21 22:38:39.123', "'22:38:39.12300'"];
$dateTests['timePrec'][] = ['value', '2021-01-21 22:38', "'22:38:00.00000'"];
$dateTests['timePrec'][] = ['value', '22:38:39.1234567', "'22:38:39.12345'"];
$dateTests['timePrec'][] = ['value', '22:38:39.123', "'22:38:39.12300'"];
$dateTests['timePrec'][] = ['value', '22:38', "'22:38:00.00000'"];
//$dateTests['timePrec'][] = ['null', null, 'NULL'];
//$dateTests['timePrec'][] = ['value', null, 'NULL'];

$dateTests['date'][] = ['value', 'now', 'NOW()'];
$dateTests['date'][] = ['now', null, 'NOW()'];
$dateTests['date'][] = ['value', '2021-01-21 22:38:39.1234567', "'2021-01-21'"];
$dateTests['date'][] = ['value', '2021-01-21 22:38:39.123', "'2021-01-21'"];
$dateTests['date'][] = ['value', '2021-01-21 22:38', "'2021-01-21'"];
$dateTests['date'][] = ['value', '22:38:39.1234567', "'" . date('Y-m-d') . "'"];
$dateTests['date'][] = ['value', '22:38:39.123', "'" . date('Y-m-d') . "'"];
$dateTests['date'][] = ['value', '22:38', "'" . date('Y-m-d') . "'"];
//$dateTests['date'][] = ['null', null, 'NULL'];
//$dateTests['date'][] = ['value', null, 'NULL'];

$dateTests['dateTime'][] = ['value', 'now', 'NOW()'];
$dateTests['dateTime'][] = ['now', null, 'NOW()'];
$dateTests['dateTime'][] = ['value', '2021-01-21 22:38:39.1234567', "'2021-01-21 22:38:39'"];
$dateTests['dateTime'][] = ['value', '2021-01-21 22:38:39.123', "'2021-01-21 22:38:39'"];
$dateTests['dateTime'][] = ['value', '2021-01-21 22:38', "'2021-01-21 22:38:00'"];
$dateTests['dateTime'][] = ['value', '22:38:39.1234567', "'" . date('Y-m-d') . " 22:38:39'"];
$dateTests['dateTime'][] = ['value', '22:38:39.123', "'" . date('Y-m-d') . " 22:38:39'"];
$dateTests['dateTime'][] = ['value', '22:38', "'" . date('Y-m-d') . " 22:38:00'"];
$dateTests['dateTime'][] = ['null', null, 'NULL'];
$dateTests['dateTime'][] = ['value', null, 'NULL'];

$dateTests['dateTimePrec'][] = ['value', 'now', 'NOW()'];
$dateTests['dateTimePrec'][] = ['now', null, 'NOW()'];
$dateTests['dateTimePrec'][] = ['value', '2021-01-21 22:38:39.1234567', "'2021-01-21 22:38:39.123'"];
$dateTests['dateTimePrec'][] = ['value', '2021-01-21 22:38:39.12', "'2021-01-21 22:38:39.120'"];
$dateTests['dateTimePrec'][] = ['value', '2021-01-21 22:38:39', "'2021-01-21 22:38:39.000'"];
$dateTests['dateTimePrec'][] = ['value', '2021-01-21 22:38', "'2021-01-21 22:38:00.000'"];
$dateTests['dateTimePrec'][] = ['value', '22:38:39.1234567', "'" . date('Y-m-d') . " 22:38:39.123'"];
$dateTests['dateTimePrec'][] = ['value', '22:38:39.120', "'" . date('Y-m-d') . " 22:38:39.120'"];
$dateTests['dateTimePrec'][] = ['value', '22:38', "'" . date('Y-m-d') . " 22:38:00.000'"];
//$dateTests['dateTimePrec'][] = ['null', null, 'NULL'];
//$dateTests['dateTimePrec'][] = ['value', null, 'NULL'];

$dateTests['timestamp'][] = ['value', 'now', 'NOW()'];
$dateTests['timestamp'][] = ['now', null, 'NOW()'];
$dateTests['timestamp'][] = ['value', '2021-01-21 22:38:39.1234567', "'2021-01-21 22:38:39'"];
$dateTests['timestamp'][] = ['value', '2021-01-21 22:38:39.123', "'2021-01-21 22:38:39'"];
$dateTests['timestamp'][] = ['value', '2021-01-21 22:38', "'2021-01-21 22:38:00'"];
$dateTests['timestamp'][] = ['value', '22:38:39.1234567', "'" . date('Y-m-d') . " 22:38:39'"];
$dateTests['timestamp'][] = ['value', '22:38:39.123', "'" . date('Y-m-d') . " 22:38:39'"];
$dateTests['timestamp'][] = ['value', '22:38', "'" . date('Y-m-d') . " 22:38:00'"];
$dateTests['timestamp'][] = ['null', null, 'NULL'];
$dateTests['timestamp'][] = ['value', null, 'NULL'];

$dateTests['timestampPrec'][] = ['value', 'now', 'NOW()'];
$dateTests['timestampPrec'][] = ['now', null, 'NOW()'];
$dateTests['timestampPrec'][] = ['value', '2021-01-21 22:38:39.1234567', "'2021-01-21 22:38:39.1234'"];
$dateTests['timestampPrec'][] = ['value', '2021-01-21 22:38:39.12', "'2021-01-21 22:38:39.1200'"];
$dateTests['timestampPrec'][] = ['value', '2021-01-21 22:38:39', "'2021-01-21 22:38:39.0000'"];
$dateTests['timestampPrec'][] = ['value', '2021-01-21 22:38', "'2021-01-21 22:38:00.0000'"];
$dateTests['timestampPrec'][] = ['value', '22:38:39.1234567', "'" . date('Y-m-d') . " 22:38:39.1234'"];
$dateTests['timestampPrec'][] = ['value', '22:38:39.120', "'" . date('Y-m-d') . " 22:38:39.1200'"];
$dateTests['timestampPrec'][] = ['value', '22:38', "'" . date('Y-m-d') . " 22:38:00.0000'"];
//$dateTests['timestampPrec'][] = ['null', null, 'NULL'];
//$dateTests['timestampPrec'][] = ['value', null, 'NULL'];