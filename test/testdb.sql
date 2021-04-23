CREATE TABLE `all_fields` (
    `ID` int(11) NOT NULL,
    `TID` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Poesis::transactionID',
    `nullField` datetime DEFAULT NULL,
    `varchar` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT '',
    `varchar2` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `year` year(4) DEFAULT NULL,
    `year2` year(2) DEFAULT NULL,
    `time` time DEFAULT NULL,
    `timePrec` time(5) NOT NULL DEFAULT '00:00:00.00000',
    `date` date NOT NULL DEFAULT '0000-00-00',
    `timestamp` timestamp NULL DEFAULT NULL,
    `dateTime` datetime DEFAULT current_timestamp(),
    `dateTimePrec` datetime(3) NOT NULL DEFAULT current_timestamp(3),
    `tinyText` tinytext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `text` text COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `mediumText` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `longText` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tinyInt` tinyint(4) NOT NULL DEFAULT 0,
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

INSERT INTO `all_fields` (`ID`, `TID`, `nullField`, `varchar`, `varchar2`, `year`, `year2`, `time`, `timePrec`, `date`, `timestamp`, `dateTime`, `dateTimePrec`, `tinyText`, `text`, `mediumText`, `longText`, `tinyInt`, `smallInt`, `mediumInt`, `int`, `decimal`, `float`, `double`, `real`, `bit`, `tinyBlob`, `blog`, `mediumBlob`, `longBlog`, `enum`, `set`) VALUES
(1, NULL, NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-04-15 17:11:02', '2021-04-15 20:11:02', '2021-04-15 20:11:02.455', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

CREATE TABLE `all_fields_dup` (
    `ID` int(11) NOT NULL,
    `nullField` datetime DEFAULT NULL,
    `varchar` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT '',
    `varchar2` varchar(125) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `year` year(4) DEFAULT NULL,
    `year2` year(2) DEFAULT NULL,
    `time` time DEFAULT NULL,
    `timePrec` time(5) NOT NULL DEFAULT '00:00:00.00000',
    `date` date NOT NULL DEFAULT '0000-00-00',
    `timestamp` timestamp NULL DEFAULT NULL,
    `dateTime` datetime DEFAULT current_timestamp(),
    `dateTimePrec` datetime(3) NOT NULL DEFAULT current_timestamp(3),
    `tinyText` tinytext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `text` text COLLATE utf8mb4_unicode_ci DEFAULT '0',
    `mediumText` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `longText` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tinyInt` tinyint(4) NOT NULL DEFAULT 0,
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
  `set` set('a','b') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TID` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Poesis::transactionID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `all_fields_dup` (`ID`, `nullField`, `varchar`, `varchar2`, `year`, `year2`, `time`, `timePrec`, `date`, `timestamp`, `dateTime`, `dateTimePrec`, `tinyText`, `text`, `mediumText`, `longText`, `tinyInt`, `smallInt`, `mediumInt`, `int`, `decimal`, `float`, `double`, `real`, `bit`, `tinyBlob`, `blog`, `mediumBlob`, `longBlog`, `enum`, `set`, `TID`) VALUES
(1, NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

CREATE TABLE `db_log` (
    `ID` bigint(11) UNSIGNED NOT NULL,
    `userID` int(11) NOT NULL DEFAULT 0,
    `data` longblob DEFAULT NULL,
    `tableName` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tableRowID` int(11) DEFAULT NULL,
    `eventName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `insertDateTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `microTime` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `url` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ip` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `all_fields`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `transactionID` (`TID`);

ALTER TABLE `all_fields_dup`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `transactionID` (`TID`);

ALTER TABLE `db_log`
    ADD PRIMARY KEY (`ID`),
  ADD KEY `tableName` (`tableName`,`tableRowID`,`eventName`),
  ADD KEY `userID` (`userID`);

ALTER TABLE `all_fields`
    MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `all_fields_dup`
    MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `db_log`
    MODIFY `ID` bigint(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;
