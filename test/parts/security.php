<?php
$db = new TAllFields();
$db->varchar("Ed O'Neil");
$db->varchar2("SQL_INJECTION ';'alter table xyz';");
checkQuery($db->getSelectQuery(), "SELECT * FROM `all_fields` WHERE `varchar` = 'Ed O\'Neil' AND `varchar2` = 'SQL_INJECTION \';\'alter table xyz\';'");