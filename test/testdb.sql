DROP TABLE IF EXISTS `all_fields`;
CREATE TABLE `all_fields` (
    `ID` int(11) NOT NULL,
    `TID` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Poesis::transactionID',
    `UUID` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Poesis::UUID',
    `nullField` datetime DEFAULT NULL,
    `varchar` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT '',
    `varchar2` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `year` year(4) DEFAULT NULL,
    `year2` year(2) DEFAULT NULL,
    `time` time DEFAULT NULL,
    `timePrec` time(5) NOT NULL DEFAULT '00:00:00.00000',
    `date` date NOT NULL DEFAULT '0000-00-00',
    `timestamp` timestamp NULL DEFAULT NULL,
    `timestampPrec` timestamp(4) NOT NULL DEFAULT current_timestamp(4),
    `dateTime` datetime DEFAULT current_timestamp(),
    `dateTimePrec` datetime(3) NOT NULL DEFAULT current_timestamp(3),
    `tinyText` tinytext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `text` text COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `mediumText` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `longText` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tinyInt` tinyint(1) NOT NULL DEFAULT 0,
    `smallInt` smallint(6) NOT NULL DEFAULT 0,
    `mediumInt` mediumint(9) NOT NULL DEFAULT 0,
    `int` int(11) NOT NULL DEFAULT 0,
    `decimal` decimal(10,3) NOT NULL DEFAULT 0.000,
    `float` float(10,3) NOT NULL DEFAULT 0.000,
  `double` double(10,3) NOT NULL DEFAULT 0.000,
  `real` double(10,3) NOT NULL DEFAULT 0.000,
  `bit` bit(3) DEFAULT NULL,
  `tinyBlob` tinyblob DEFAULT NULL,
  `blog` blob DEFAULT NULL,
  `mediumBlob` mediumblob DEFAULT NULL,
  `longBlog` longblob DEFAULT NULL,
  `enum` enum('a','b') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `set` set('a','b') COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TRIGGER IF EXISTS `before_insert_all_fields_dup`;
DELIMITER ;;
CREATE TRIGGER `before_insert_all_fields_dup` BEFORE INSERT ON `all_fields` FOR EACH ROW BEGIN
    IF new.UUID IS NULL THEN
        SET new.UUID = uuid();
END IF;
END
;;
DELIMITER ;
ALTER TABLE `all_fields` ADD PRIMARY KEY (`ID`),ADD UNIQUE KEY `UUID` (`UUID`),ADD KEY `transactionID` (`TID`);
ALTER TABLE `all_fields` MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;
INSERT INTO `all_fields` (`nullField`, `varchar`, `varchar2`, `year`, `year2`, `time`, `timePrec`, `date`, `timestamp`, `dateTime`, `dateTimePrec`, `tinyText`, `text`, `mediumText`, `longText`, `tinyInt`, `smallInt`, `mediumInt`, `int`, `decimal`, `float`, `double`, `real`, `bit`, `tinyBlob`, `blog`, `mediumBlob`, `longBlog`, `enum`, `set`, `TID`) VALUES(NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),(NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `multi_prim_key`;
CREATE TABLE `multi_prim_key` (
    `someID` int(11) NOT NULL DEFAULT 0,
    `someKey` enum('value1','value2','value3') COLLATE utf8mb4_unicode_ci NOT NULL,
    `someValue` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `multi_prim_key` ADD PRIMARY KEY (`someID`,`someKey`);


DROP TABLE IF EXISTS `db_log`;
CREATE TABLE `db_log` (
    `ID` bigint(11) UNSIGNED NOT NULL,
    `dataID` bigint(11) UNSIGNED NOT NULL DEFAULT 0,
    `rowIDColValues` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `db_log` ADD PRIMARY KEY (`ID`),ADD KEY `dataFor` (`dataID`),ADD KEY `rowIDColValues` (`rowIDColValues`);
ALTER TABLE `db_log` MODIFY `ID` bigint(11) UNSIGNED NOT NULL AUTO_INCREMENT;

DROP TABLE IF EXISTS `db_log_data`;
CREATE TABLE `db_log_data` (
    `ID` bigint(11) UNSIGNED NOT NULL,
    `ts` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
    `data` longblob DEFAULT NULL,
    `userID` int(11) NOT NULL DEFAULT 0,
    `eventName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tableName` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `rowIDCols` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'How to identryfy row',
    `url` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ip` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `db_log_data`  ADD PRIMARY KEY (`ID`),  ADD KEY `userID` (`userID`),  ADD KEY `tableRow` (`tableName`,`rowIDCols`) USING BTREE;
ALTER TABLE `db_log_data` MODIFY `ID` bigint(11) UNSIGNED NOT NULL AUTO_INCREMENT;



DROP VIEW IF EXISTS `v_db_log`;
CREATE ALGORITHM = MERGE  VIEW `v_db_log` AS
SELECT
    dl.ID,
    dld.ts,
    dld.data,
    dld.userID,
    dld.eventName,
    dld.tableName,
    dld.rowIDCols,
    dl.rowIDColValues,
    dld.url,
    dld.ip
FROM
    db_log AS dl
        LEFT JOIN db_log_data AS dld ON dld.ID = dl.dataID
;

