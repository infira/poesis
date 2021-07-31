CREATE TABLE `all_fields` (
    `ID`            INT(11) NOT NULL,
    `nullField`     DATETIME                                         DEFAULT NULL,
    `varchar`       VARCHAR(25) COLLATE utf8mb4_unicode_ci           DEFAULT '',
    `varchar2`      VARCHAR(125) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `year` YEAR(4) DEFAULT NULL,
    `year2` YEAR(2) DEFAULT NULL,
    `time`          TIME                                             DEFAULT NULL,
    `timePrec`      TIME(5)                                 NOT NULL DEFAULT '00:00:00.00000',
    `date`          DATE                                    NOT NULL DEFAULT '0000-00-00',
    `timestamp`     TIMESTAMP NULL DEFAULT NULL,
    `timestampPrec` TIMESTAMP(4)                            NOT NULL DEFAULT current_timestamp(4),
    `dateTime`      DATETIME                                         DEFAULT current_timestamp(),
    `dateTimePrec`  DATETIME(3) NOT NULL DEFAULT current_timestamp (3),
    `tinyText`      TINYTEXT COLLATE utf8mb4_unicode_ci              DEFAULT NULL,
    `text`          TEXT COLLATE utf8mb4_unicode_ci                  DEFAULT '0',
    `mediumText`    MEDIUMTEXT COLLATE utf8mb4_unicode_ci            DEFAULT NULL,
    `longText`      LONGTEXT COLLATE utf8mb4_unicode_ci              DEFAULT NULL,
    `tinyInt`       TINYINT(1) NOT NULL DEFAULT 0,
    `smallInt`      SMALLINT(6) NOT NULL DEFAULT 0,
    `mediumInt`     MEDIUMINT(9) NOT NULL DEFAULT 0,
    `int`           INT(11) NOT NULL DEFAULT 0,
    `decimal`       DECIMAL(10, 3)                          NOT NULL DEFAULT 0.000,
    `float`         FLOAT(10, 3
) NOT NULL DEFAULT 0.000,
	`double`        DOUBLE(10, 3)                           NOT NULL DEFAULT 0.000,
	`real`          DOUBLE(10, 3)                           NOT NULL DEFAULT 0.000,
	`bit`           BIT(3)                                           DEFAULT NULL,
	`tinyBlob`      TINYBLOB                                         DEFAULT NULL,
	`blog`          BLOB                                             DEFAULT NULL,
	`mediumBlob`    MEDIUMBLOB                                       DEFAULT NULL,
	`longBlog`      LONGBLOB                                         DEFAULT NULL,
	`enum`          ENUM ('a','b') COLLATE utf8mb4_unicode_ci        DEFAULT NULL,
	`set`           SET ('a','b') COLLATE utf8mb4_unicode_ci         DEFAULT NULL
) ENGINE = InnoDB
	DEFAULT CHARSET = utf8mb4
	COLLATE = utf8mb4_unicode_ci;

ALTER TABLE `all_fields`
    ADD PRIMARY KEY (`ID`);

ALTER TABLE `all_fields`
    MODIFY `ID` INT (11) NOT NULL AUTO_INCREMENT;

INSERT INTO `all_fields` (`nullField`, `varchar`, `varchar2`, `year`, `year2`, `time`, `timePrec`, `date`, `timestamp`, `dateTime`, `dateTimePrec`, `tinyText`, `text`, `mediumText`, `longText`, `tinyInt`, `smallInt`, `mediumInt`, `int`, `decimal`, `float`, `double`, `real`, `bit`, `tinyBlob`,
                          `blog`, `mediumBlob`, `longBlog`, `enum`, `set`)
VALUES (NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
    (NULL, 'testAutoSave', '', NULL, NULL, NULL, '00:00:00.00000', '0000-00-00', '2021-03-22 13:45:47', '2021-03-22 15:45:47', '2021-03-22 15:45:47.063', NULL, '0', NULL, NULL, 0, 0, 0, 0, '0.000', 0.000, 0.000, 0.000, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `multi_prim_key`;
CREATE TABLE `multi_prim_key` (
    `someID`    INT(11) NOT NULL DEFAULT 0,
    `someKey`   ENUM ('value1','value2','value3') COLLATE utf8mb4_unicode_ci NOT NULL,
    `someValue` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE = InnoDB
	DEFAULT CHARSET = utf8mb4
	COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `multi_prim_key`
    ADD PRIMARY KEY (`someID`, `someKey`);

DROP TABLE IF EXISTS `tid`;
CREATE TABLE `tid` (
    `ID`      int(11) NOT NULL,
    `TID`     char(32) COLLATE utf8mb4_unicode_ci    DEFAULT NULL COMMENT 'Poesis::transactionID',
    `varchar` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `tid`
    ADD PRIMARY KEY (`ID`),ADD KEY `transactionID` (`TID`);
ALTER TABLE `tid` MODIFY `ID` int (11) NOT NULL AUTO_INCREMENT;

DROP TABLE IF EXISTS `no_prim`;
CREATE TABLE `no_prim` (
    `key` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT '',
    `dateTime`      DATETIME                                         DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `db_log`;
CREATE TABLE `db_log` (
    `ID`             BIGINT(11) UNSIGNED NOT NULL,
    `ts`             TIMESTAMP(6) NOT NULL                   DEFAULT current_timestamp(6) ON UPDATE current_timestamp (6),
    `data`           LONGBLOB                                DEFAULT NULL,
    `userID`         INT(11) NOT NULL DEFAULT 0,
    `eventName`      VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `tableName`      VARCHAR(50) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `rowIDCols`      VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'How to identryfy row',
    `rowIDColValues` LONGTEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `url`            MEDIUMTEXT COLLATE utf8mb4_unicode_ci   DEFAULT NULL,
    `ip`             VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE = InnoDB
	DEFAULT CHARSET = utf8mb4
	COLLATE = utf8mb4_unicode_ci;
ALTER TABLE `db_log`
    ADD PRIMARY KEY (`ID`),
	ADD KEY `userID`(`userID`),
	ADD KEY `tableRow`(`tableName`, `rowIDCols`) USING BTREE;
ALTER TABLE `db_log`
    MODIFY `ID` BIGINT(11) UNSIGNED NOT NULL AUTO_INCREMENT;


