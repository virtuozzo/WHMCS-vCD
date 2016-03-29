CREATE TABLE IF NOT EXISTS `{moduleName}_Users` (
		`id`            INT(11) UNSIGNED            NOT NULL AUTO_INCREMENT,
		`serviceID`     MEDIUMINT(11) UNSIGNED      NOT NULL,
		`WHMCSUserID`   MEDIUMINT(11) UNSIGNED      NOT NULL,
		`OnAppUserID`   MEDIUMINT(11) UNSIGNED      NOT NULL,
		`serverID`      MEDIUMINT(11) UNSIGNED      NOT NULL,
		`billingType`   ENUM('postpaid', 'prepaid') NOT NULL DEFAULT 'postpaid',
		PRIMARY KEY (`id`),
		-- UNIQUE KEY `user_constrain` (`serviceID`,`WHMCSUserID`,`OnAppUserID`),
		UNIQUE KEY `user_integrity_constrain` (`OnAppUserID`, `serverID`)
)
		ENGINE = InnoDB
		DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `{moduleName}_Cache` (
		`id`     MEDIUMINT(11) UNSIGNED            NOT NULL AUTO_INCREMENT,
		`itemID` MEDIUMINT(11)                     NOT NULL,
		`type`   ENUM('serverData', 'invoiceData') NOT NULL DEFAULT 'serverData',
		`data`   MEDIUMTEXT                        NOT NULL,
		PRIMARY KEY (`id`)
)
		ENGINE = InnoDB
		DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `{moduleName}_Hourly_LastCheck` (
		`serverID`    INT(10) UNSIGNED NOT NULL,
		`WHMCSUserID` INT(10) UNSIGNED NOT NULL,
		`OnAppUserID` INT(10) UNSIGNED NOT NULL,
		`Date`        DATETIME         NOT NULL,
		UNIQUE KEY `integrety` (`serverID`, `WHMCSUserID`, `OnAppUserID`)
)
		ENGINE = InnoDB
		DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `{moduleName}_Hourly_Stat` (
		`id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`serverID`    INT(11)                   DEFAULT NULL,
		`WHMCSUserID` INT(11)                   DEFAULT NULL,
		`OnAppUserID` INT(11)                   DEFAULT NULL,
		`cost`        DOUBLE(20, 12)            DEFAULT NULL,
		`startDate`   DATETIME                  DEFAULT NULL,
		`endDate`     DATETIME                  DEFAULT NULL,
		PRIMARY KEY (`id`)
)
		ENGINE = InnoDB
		DEFAULT CHARSET = utf8;